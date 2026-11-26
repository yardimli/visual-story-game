<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>{{ $story->title }} · Playtest</title>
    @vite(['resources/css/app.css', 'resources/js/player.js'])
</head>

<body class="min-h-screen bg-stone-950 text-white">
    <main
        x-data="gamePlayer(@js($game))"
        class="grid min-h-screen place-items-center"
    >
        <div
            x-show="!preview"
            class="space-y-5 p-8 text-center"
        >
            <h1 class="font-serif text-3xl">{{ $story->title }}</h1><button
                type="button"
                class="btn-primary"
                @click="begin()"
            >{{ ($publicPlay ?? false) ? 'Start game' : 'Start playtest' }}</button>
        </div>
        @include('game.stage')
    </main><x-toasts />
</body>

</html>
