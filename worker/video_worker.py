"""Pull video jobs, run a registered ComfyUI workflow, and upload the MP4.

The journal is written before submission. An uncertain submission is reconciled
against ComfyUI's queue/history; it is never automatically submitted twice.
"""

import copy
from datetime import datetime, timezone
import hashlib
import json
import logging
import os
from pathlib import Path
import time
import uuid

import requests
from dotenv import load_dotenv
from requests_toolbelt.multipart.encoder import MultipartEncoder
import websocket

ROOT = Path(__file__).resolve().parents[1]
STATE = ROOT / "worker" / "state"
LOG = logging.getLogger("video-worker")


class WorkflowError(Exception):
    """A definite validation or generation failure, rather than a lost connection."""


class JobGone(Exception):
    """The server no longer has this job (for example its story was deleted)."""


def build_workflow(engine, job, first, last, object_info):
    filename = engine["last_frame_workflow"] if last else engine["workflow"]
    graph = json.loads((ROOT / "comfyui" / filename).read_text(encoding="utf-8"))
    graph = copy.deepcopy(graph)
    bindings = engine["bindings"]

    def assign(binding, value):
        graph[binding["node"]]["inputs"][binding["input"]] = value

    for name, value in {"prompt": job["prompt"], "seed": job["seed"],
                        "first_frame": first, "output": "video/vsg_" + job["id"]}.items():
        assign(bindings[name], value)
    if last:
        assign(bindings["last_frame"], last)
    for key, parameter in engine["parameters"].items():
        value = job["parameters"][key]
        if parameter["type"] == "select":
            value = parameter["options"][value]
            node_type = graph[parameter["node"]]["class_type"]
            schema = object_info.get(node_type, {}).get("input", {})
            definition = {**schema.get("required", {}), **schema.get("optional", {})}.get(parameter["input"], [])
            choices = definition[0] if definition and isinstance(definition[0], list) else []
            if choices and value not in choices:
                # ResolutionSelector versions differ in their descriptive suffixes.
                matches = [choice for choice in choices if choice.split(" ")[0] == job["parameters"][key]]
                if len(matches) != 1:
                    raise WorkflowError(f"Unsupported {key}: {value}")
                value = matches[0]
        assign(parameter, value)
    for node in graph.values():
        if node["class_type"] not in object_info:
            raise WorkflowError(f"ComfyUI node is missing: {node['class_type']}")
    for node_id, inputs in engine.get("overrides", {}).items():
        graph[node_id]["inputs"].update(inputs)
    return graph


def find_prompt(job_id, queue, history):
    entries = queue.get("queue_running", []) + queue.get("queue_pending", [])
    entries += [entry.get("prompt", []) for entry in history.values()]
    for entry in entries:
        if len(entry) > 3 and entry[3].get("vsg_job_id") == job_id:
            return entry[1]
    return None


def output_video(history, output_node):
    output = history.get("outputs", {}).get(output_node, {})
    for values in output.values():
        if not isinstance(values, list):
            continue
        for item in values:
            if isinstance(item, dict) and str(item.get("filename", "")).lower().endswith(".mp4"):
                return {key: item[key] for key in ["filename", "subfolder", "type"] if key in item}
    return None


class Worker:
    def __init__(self):
        load_dotenv(ROOT / "worker" / ".env")
        self.server = os.environ["VIDEO_SERVER_URL"].rstrip("/") + "/api/video/worker"
        if not self.server.startswith("https://"):
            raise ValueError("VIDEO_SERVER_URL must use HTTPS.")
        self.comfy = os.getenv("COMFYUI_URL", "http://127.0.0.1:8188").rstrip("/")
        self.worker_id = os.environ["VIDEO_WORKER_ID"]
        self.session = requests.Session()
        self.session.headers.update({"Authorization": "Bearer " + os.environ["VIDEO_WORKER_TOKEN"], "Accept": "application/json"})
        self.engines = json.loads((ROOT / "comfyui" / "engines.json").read_text(encoding="utf-8"))
        STATE.mkdir(parents=True, exist_ok=True)
        self.journal = STATE / "active.json"
        self.state = json.loads(self.journal.read_text()) if self.journal.exists() else None

    def save(self):
        temporary = self.journal.with_suffix(".tmp")
        temporary.write_text(json.dumps(self.state), encoding="utf-8")
        temporary.replace(self.journal)

    def api(self, method, path, **kwargs):
        headers = kwargs.pop("headers", {})
        if self.state:
            headers["X-Video-Claim"] = self.state["job"]["claim_key"]
        response = self.session.request(method, self.server + path, headers=headers, timeout=(15, 300), allow_redirects=False, **kwargs)
        if response.is_redirect:
            raise WorkflowError("Video server redirected the API request. Check VIDEO_SERVER_URL.")
        if response.status_code == 404 and self.state:
            raise JobGone("The server no longer has this job or claim.")
        if response.status_code in [400, 409, 413, 422]:
            try:
                message = response.json().get("message", response.reason)
            except ValueError:
                message = response.reason
            raise WorkflowError(f"Video server rejected the request ({response.status_code}): {message}")
        response.raise_for_status()
        return response

    def comfy_get(self, path):
        response = requests.get(self.comfy + path, timeout=30)
        response.raise_for_status()
        return response.json()

    def heartbeat(self, status, progress=0, error=None):
        job = self.state["job"]
        response = self.api("POST", f"/{job['id']}/heartbeat", json={
            "status": status, "progress": progress,
            "prompt_id": self.state.get("prompt_id"), "error": error,
            "started_at": self.state.get("started_at"),
        }).json()
        self.state["status"] = response["status"]
        self.save()
        return response["status"]

    def upload_frame(self, frame):
        job = self.state["job"]
        response = self.api("GET", f"/{job['id']}/frames/{frame}")
        response = requests.post(self.comfy + "/upload/image", files={
            "image": (f"vsg_{job['id']}_{frame}.png", response.content, response.headers.get("Content-Type", "image/png")),
        }, data={"type": "input", "overwrite": "true"}, timeout=120)
        response.raise_for_status()
        image = response.json()
        return "/".join(filter(None, [image.get("subfolder"), image["name"]]))

    def submit(self):
        job = self.state["job"]
        if self.state.get("submission_attempted"):
            prompt_id = find_prompt(job["id"], self.comfy_get("/queue"), self.comfy_get("/history"))
            if not prompt_id:
                raise WorkflowError("Submission outcome is unknown and ComfyUI has no matching history. Check ComfyUI before creating another job; this job was not resubmitted.")
            self.state["prompt_id"] = prompt_id
            self.save()
            return
        engine = self.engines[job["engine"]]
        if job["engine_version"] != engine["version"]:
            raise WorkflowError("Engine version mismatch. Update this repository clone.")
        first = self.upload_frame("first")
        last = self.upload_frame("last") if job["has_last_frame"] else None
        graph = build_workflow(engine, job, first, last, self.comfy_get("/object_info"))
        self.heartbeat("submitting")
        self.state["submission_attempted"] = True
        self.save()
        response = requests.post(self.comfy + "/prompt", json={
            "prompt": graph, "client_id": self.state["client_id"],
            "extra_data": {"vsg_job_id": job["id"]},
        }, timeout=120)
        if response.status_code == 400:
            raise WorkflowError("ComfyUI rejected the workflow: " + response.text[:3000])
        response.raise_for_status()
        self.state["prompt_id"] = response.json()["prompt_id"]
        self.save()
        self.heartbeat("submitting")

    def run_job(self):
        job = self.state["job"]
        if not self.state.get("prompt_id"):
            self.submit()
        prompt_id = self.state["prompt_id"]
        socket = None
        try:
            socket = websocket.create_connection(self.comfy.replace("http://", "ws://").replace("https://", "wss://")
                                                 + "/ws?clientId=" + self.state["client_id"], timeout=1)
        except (OSError, websocket.WebSocketException):
            LOG.info("WebSocket unavailable; queue/history polling remains active.")
        progress = 0
        last_poll = 0
        try:
            while True:
                if socket:
                    try:
                        message = socket.recv()
                        event = json.loads(message) if isinstance(message, str) else {}
                        data = event.get("data", {})
                        if event.get("type") == "progress" and data.get("prompt_id") == prompt_id:
                            progress = min(99, int(100 * data["value"] / max(1, data["max"])))
                        if event.get("type") == "execution_start" and data.get("prompt_id") == prompt_id:
                            self.record_start(data.get("timestamp"))
                    except websocket.WebSocketTimeoutException:
                        pass
                    except (OSError, websocket.WebSocketException):
                        socket.close()
                        socket = None
                else:
                    time.sleep(1)
                if time.monotonic() - last_poll < 10:
                    continue
                last_poll = time.monotonic()
                history = self.comfy_get("/history/" + prompt_id).get(prompt_id)
                if history:
                    status = history.get("status", {})
                    for name, details in status.get("messages", []):
                        if name == "execution_start":
                            self.record_start(details.get("timestamp"))
                    if status.get("status_str") == "error":
                        raise WorkflowError("ComfyUI execution failed: " + json.dumps(status.get("messages", []))[:3000])
                    if status.get("completed"):
                        output = output_video(history, self.engines[job["engine"]]["bindings"]["output"]["node"])
                        if not output:
                            raise WorkflowError("ComfyUI finished without an MP4 output from the configured output node.")
                        self.finish(output)
                        return
                queue = self.comfy_get("/queue")
                running = any(entry[1] == prompt_id for entry in queue.get("queue_running", []))
                pending = any(entry[1] == prompt_id for entry in queue.get("queue_pending", []))
                if not history and not running and not pending:
                    raise WorkflowError("ComfyUI no longer has this request. It was not automatically resubmitted.")
                state = "running" if running or self.state.get("status") == "running" else "submitting"
                if self.heartbeat(state, progress) in ["failed", "completed"]:
                    return
        finally:
            if socket:
                socket.close()

    def record_start(self, timestamp):
        if timestamp and not self.state.get("started_at"):
            self.state["started_at"] = datetime.fromtimestamp(timestamp / 1000, timezone.utc).isoformat()
            self.save()

    def finish(self, output):
        job = self.state["job"]
        if self.heartbeat("uploading", 99) in ["completed", "failed"]:
            return
        path = STATE / (job["id"] + ".mp4")
        with requests.get(self.comfy + "/view", params=output, stream=True, timeout=120) as response:
            response.raise_for_status()
            digest = hashlib.sha256()
            with path.open("wb") as stream:
                for chunk in response.iter_content(1024 * 1024):
                    stream.write(chunk)
                    digest.update(chunk)
        with path.open("rb") as stream:
            multipart = MultipartEncoder(fields={"sha256": digest.hexdigest(), "video": ("result.mp4", stream, "video/mp4")})
            self.api("POST", f"/{job['id']}/complete", data=multipart, headers={"Content-Type": multipart.content_type})
        path.unlink(missing_ok=True)
        LOG.info("Completed video %s", job["id"])

    def run(self):
        while True:
            try:
                if not self.state:
                    response = self.api("POST", "/claim", json={"worker_id": self.worker_id,
                        "engines": {key: engine["version"] for key, engine in self.engines.items()}})
                    if response.status_code == 204:
                        time.sleep(int(os.getenv("VIDEO_POLL_SECONDS", "10")))
                        continue
                    job = response.json()
                    self.state = {"job": job, "client_id": str(uuid.uuid4()), "status": job["status"],
                                  "prompt_id": job.get("prompt_id"), "submission_attempted": job["status"] != "claimed"}
                    self.save()
                    LOG.info("Claimed video %s (%s)", job["id"], job["engine"])
                try:
                    self.run_job()
                except WorkflowError as error:
                    LOG.error("%s", error)
                    self.heartbeat("failed", error=str(error)[:4000])
                self.journal.unlink(missing_ok=True)
                self.state = None
            except (requests.RequestException, OSError, ValueError) as error:
                LOG.warning("Connection or response problem; retaining job and retrying: %s", error)
                time.sleep(10)
            except JobGone as error:
                LOG.warning("%s Moving to the next server job.", error)
                self.journal.unlink(missing_ok=True)
                self.state = None


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    # Hold an OS lock: two processes must not race the same journal/submission.
    STATE.mkdir(parents=True, exist_ok=True)
    with (STATE / "worker.lock").open("a+b") as lock:
        lock.seek(0)
        lock.write(b"0")
        lock.flush()
        lock.seek(0)
        if os.name == "nt":
            import msvcrt
            msvcrt.locking(lock.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            import fcntl
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        Worker().run()
