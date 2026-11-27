import json
import unittest
from unittest.mock import Mock, patch

from video_worker import ROOT, Worker, WorkflowError, build_workflow, find_prompt, output_video


class WorkflowTests(unittest.TestCase):
    def setUp(self):
        self.engine = json.loads((ROOT / "comfyui/engines.json").read_text())["fastvideo_h3"]
        graph = json.loads((ROOT / "comfyui" / self.engine["workflow"]).read_text())
        self.info = {node["class_type"]: {} for node in graph.values()}
        self.job = {"id": "test-job", "prompt": "Open the door", "seed": 123,
                    "parameters": {"aspect_ratio": "16:9", "seconds": 15, "megapixels": 0.8}}

    def test_first_only_preserves_sampler_and_duration_formula(self):
        graph = build_workflow(self.engine, self.job, "first.png", None, self.info)
        self.assertNotIn("last_frame", graph["105:104"]["inputs"])
        self.assertEqual(graph["149"]["inputs"]["image"], "first.png")
        self.assertEqual(graph["105:111"]["inputs"]["value"], 15)
        self.assertIn("% 17", graph["105:107"]["inputs"]["expression"])
        self.assertEqual(graph["105:15"]["inputs"]["noise_seed"], 123)
        self.assertEqual(graph["92"]["inputs"]["format"], "mp4")

    def test_reference_engine_expands_nine_images_and_anchors_both_frames(self):
        engine = json.loads((ROOT / "comfyui/engines.json").read_text())["minimax_h3_reference"]
        source = json.loads((ROOT / "comfyui" / engine["workflow"]).read_text())
        info = {node["class_type"]: {} for node in source.values()}
        info["MiniMaxH3AddGuide"] = {}
        self.job["parameters"]["steps"] = 25
        references = [f"character_{i}.png" for i in range(9)]
        graph = build_workflow(engine, self.job, "room.png", "chest.png", info, references)
        for i, filename in enumerate(references):
            link = graph["136"]["inputs"][f"ref_images.ref_image_{i}"]
            self.assertEqual(graph[link[0]]["inputs"]["image"], filename)
        self.assertEqual(graph["vsg_first_guide"]["inputs"]["frame_idx"], 0)
        self.assertEqual(graph["vsg_last_guide"]["inputs"]["frame_idx"], -1)
        self.assertEqual(graph["vsg_last_guide"]["inputs"]["positive"], ["vsg_first_guide", 0])
        self.assertEqual(graph["126"]["inputs"]["conditioning"], ["vsg_last_guide", 0])
        self.assertEqual(graph["125"]["inputs"]["latent_image"], ["136", 1])
        self.assertEqual(graph["143"]["inputs"]["value"], 25)
        self.assertNotIn("137", graph)
        self.assertNotIn("139", graph)
        with self.assertRaisesRegex(WorkflowError, "Too many reference"):
            build_workflow(engine, self.job, "room.png", None, info, references + ["extra.png"])
        del info["MiniMaxH3AddGuide"]
        with self.assertRaisesRegex(WorkflowError, "MiniMaxH3AddGuide"):
            build_workflow(engine, self.job, "room.png", None, info, references)

    def test_reference_engine_first_only_has_no_sample_or_last_image(self):
        engine = json.loads((ROOT / "comfyui/engines.json").read_text())["minimax_h3_reference"]
        source = json.loads((ROOT / "comfyui" / engine["workflow"]).read_text())
        info = {node["class_type"]: {} for node in source.values()}
        info["MiniMaxH3AddGuide"] = {}
        self.job["parameters"]["steps"] = 20
        graph = build_workflow(engine, self.job, "room.png", None, info, ["visitor.png"])
        self.assertEqual(graph["126"]["inputs"]["conditioning"], ["vsg_first_guide", 0])
        self.assertNotIn("vsg_last_guide", graph)
        self.assertNotIn("ref_images.ref_image_1", graph["136"]["inputs"])

    def test_last_frame_uses_supplied_example(self):
        graph = build_workflow(self.engine, self.job, "first.png", "last.png", self.info)
        self.assertEqual(graph["105:104"]["inputs"]["last_frame"], ["150", 0])
        self.assertEqual(graph["150"]["inputs"]["image"], "last.png")

    def test_aspect_ratios_match_resolution_selector(self):
        choices = ["1:1 (Square)", "16:9 (Widescreen)", "9:16 (Portrait Widescreen)",
                   "4:3 (Standard)", "3:4 (Portrait Standard)"]
        for ratio, expected in zip(self.engine["parameters"]["aspect_ratio"]["options"], choices):
            with self.subTest(ratio=ratio):
                self.job["parameters"]["aspect_ratio"] = ratio
                graph = build_workflow(self.engine, self.job, "first.png", None, self.info)
                self.assertEqual(graph["143"]["inputs"]["aspect_ratio"], expected)

    def test_aspect_ratio_adapts_to_legacy_and_combo_schemas(self):
        for definition in [[["16:9 (Landscape)"]],
                           ["COMBO", {"options": ["16:9 (Landscape)"]}]]:
            with self.subTest(definition=definition):
                self.info["ResolutionSelector"] = {"input": {"required": {"aspect_ratio": definition}}}
                graph = build_workflow(self.engine, self.job, "first.png", None, self.info)
                self.assertEqual(graph["143"]["inputs"]["aspect_ratio"], "16:9 (Landscape)")

    def test_unsupported_combo_ratio_fails_before_submission(self):
        self.info["ResolutionSelector"] = {"input": {"required": {
            "aspect_ratio": ["COMBO", {"options": ["1:1 (Square)"]}]}}}
        with self.assertRaisesRegex(WorkflowError, "Unsupported aspect_ratio"):
            build_workflow(self.engine, self.job, "first.png", None, self.info)

    def test_missing_nodes_fail_before_submission(self):
        with self.assertRaises(WorkflowError):
            build_workflow(self.engine, self.job, "first.png", None, {})

    def test_reconcile_submission_from_queue_and_history(self):
        entry = [1, "prompt-id", {}, {"vsg_job_id": "test-job"}, []]
        self.assertEqual(find_prompt("test-job", {"queue_pending": [entry]}, {}), "prompt-id")
        self.assertEqual(find_prompt("test-job", {}, {"prompt-id": {"prompt": entry}}), "prompt-id")
        self.assertIsNone(find_prompt("other-job", {}, {"prompt-id": {"prompt": entry}}))

    def test_output_is_only_taken_from_registered_node(self):
        history = {"outputs": {"92": {"images": [{"filename": "result.mp4", "subfolder": "video", "type": "output"}]}}}
        self.assertEqual(output_video(history, "92")["filename"], "result.mp4")
        self.assertIsNone(output_video(history, "other"))

    def test_restart_reconciles_unknown_submission_without_posting_again(self):
        worker = Worker.__new__(Worker)
        worker.state = {"job": self.job, "submission_attempted": True}
        worker.save = Mock()
        entry = [1, "existing-prompt", {}, {"vsg_job_id": "test-job"}, []]
        worker.comfy_get = lambda path: {"queue_pending": [entry]} if path == "/queue" else {}
        with patch("video_worker.requests.post") as post:
            worker.submit()
            post.assert_not_called()
        self.assertEqual(worker.state["prompt_id"], "existing-prompt")

    def test_lost_history_fails_uncertain_job_without_resubmitting(self):
        worker = Worker.__new__(Worker)
        worker.state = {"job": self.job, "submission_attempted": True}
        worker.comfy_get = lambda path: {}
        with patch("video_worker.requests.post") as post:
            with self.assertRaisesRegex(WorkflowError, "outcome is unknown"):
                worker.submit()
            post.assert_not_called()


if __name__ == "__main__":
    unittest.main()
