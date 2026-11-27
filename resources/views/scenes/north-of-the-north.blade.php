<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="theme-color"
        content="#07131e"
    >
    <title>North of the North · A fairy encounter</title>
    <link
        rel="stylesheet"
        href="{{ asset('scenes/north/scene.css') }}"
    >
    <script
        type="module"
        src="{{ asset('scenes/north/scene.js') }}"
    ></script>
</head>

<body>
    <main class="encounter">
        <div
            class="aurora"
            aria-hidden="true"
        ></div>
        <header>
            <a
                href="{{ url('/') }}"
                class="back"
            >← Visual Story Game</a>
            <span class="chapter">A TALE BEYOND THE MAPS</span>
            <button
                id="restart"
                type="button"
            >Begin again ↺</button>
        </header>
        <section
            class="title"
            aria-label="Setting"
        >
            <p class="eyebrow">THE FROZEN SHORE · ETERNAL TWILIGHT</p>
            <h1>North of<br><em>the North</em></h1>
            <p>Where the compass falls silent,<br>and the sky remembers your name.</p>
        </section>
        <section
            class="conversation"
            aria-label="Conversation with Eira"
        >
            <div class="dialogue">
                <p class="eyebrow">✧ EIRA <span>KEEPER OF THE NORTHERN LIGHTS</span></p>
                <div
                    id="reply"
                    aria-live="polite"
                    aria-atomic="true"
                    tabindex="-1"
                >
                    <p class="direction">Her wings catch the aurora like frost catching dawn.</p>
                    <p class="speech">“You have walked farther than winter, little wanderer. Tell me… did you come
                        looking for something, or hoping something would find you?”</p>
                </div>
            </div>
            <div class="choices-panel">
                <p
                    class="eyebrow"
                    id="choices-label"
                >WHAT WILL YOU ASK?</p>
                <div
                    id="choices"
                    aria-labelledby="choices-label"
                ></div>
                <p class="hint">Choose a reply · Your journey unfolds here</p>
            </div>
        </section>
        <noscript>This encounter needs JavaScript to show dialogue choices.</noscript>
    </main>
</body>

</html>
