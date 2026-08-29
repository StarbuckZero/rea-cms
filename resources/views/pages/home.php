<?php

declare(strict_types=1);
?>
<section class="max-w-3xl" aria-labelledby="welcome-heading">
    <p class="eyebrow">RealTime Efficiency API</p>
    <h1 id="welcome-heading" class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">
        A secure foundation for lightweight publishing.
    </h1>
    <p class="mt-6 max-w-2xl text-lg text-secondary">
        Rea CMS is running through its PHP front controller with server-rendered HTML and progressive enhancement.
    </p>
    <div class="mt-8 flex flex-wrap items-center gap-4">
        <button
            class="button-primary"
            type="button"
            hx-get="/fragments/welcome"
            hx-target="#htmx-result"
            hx-swap="innerHTML"
        >
            Test htmx
        </button>
        <a class="button-secondary" href="/health">View health response</a>
    </div>
    <div id="htmx-result" class="mt-6 min-h-16" aria-live="polite"></div>
</section>
