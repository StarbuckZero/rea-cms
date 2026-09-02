<?php

declare(strict_types=1);

namespace ReaCms\Api\Template;

use ReaCms\Api\Serialization\JsonSerializer;
use ReaCms\Core\Http\Response;
use ReaCms\Plugin\PluginException;
use ReaCms\Plugin\SafeTemplate;

final class PluginApiRenderer
{
    public function __construct(
        private readonly PluginApiTemplateRepository $templates,
        private readonly SafeTemplate $renderer = new SafeTemplate(),
    ) {
    }

    /** @param array<string, mixed> $document */
    public function render(
        string $pluginId,
        string $binding,
        string $format,
        string $mode,
        array $document,
    ): ?Response {
        if ($format === 'json') {
            return (new JsonSerializer())->serialize($document);
        }
        if (!in_array($format, ['html', 'txt'], true) || !in_array($mode, ['list', 'detail'], true)) {
            return null;
        }
        if (preg_match('/^[a-z][a-zA-Z0-9_-]{1,31}$/D', $binding) !== 1) {
            throw new PluginException('The API template binding name is invalid.');
        }

        $template = $this->templates->template($pluginId, $format, $mode);
        $data = $document['data'] ?? null;
        $items = $mode === 'list' && is_array($data) && array_is_list($data) ? $data : [$data];
        $rendered = [];
        foreach ($items as $item) {
            $bound = is_array($item) ? $item : ['value' => $item];
            if (is_array($bound[$binding] ?? null)) {
                $bound = [...$bound, ...$bound[$binding]];
            }
            $context = [$binding => $bound];
            $rendered[] = $format === 'html'
                ? $this->renderer->render($template, $context)
                : $this->renderer->renderText($template, $context);
        }

        if ($format === 'html') {
            return Response::html(implode("\n", $rendered));
        }
        $body = trim(implode("\n", array_filter($rendered, static fn (string $item): bool => $item !== '')));

        return new Response($body === '' ? '' : $body . "\n", 200, [
            'Content-Type' => 'text/plain; charset=US-ASCII',
        ]);
    }
}
