<?php

namespace App\Services\Communication;

class TemplateRenderer
{
    /**
     * Render a template string with given variables and optional branding.
     *
     * @param string $template
     * @param array $variables
     * @param array $branding Optional branding (header, footer, school_name)
     * @return string
     */
    public function render(string $template, array $variables = [], array $branding = []): string
    {
        $rendered = $template;

        // Auto-merge school name into variables if branding provides it
        if (!empty($branding['school_name']) && empty($variables['school_name'])) {
            $variables['school_name'] = $branding['school_name'];
        }

        foreach ($variables as $key => $val) {
            $stringVal = is_scalar($val) ? (string) $val : json_encode($val);
            $rendered = str_replace(["{{{$key}}}", "{{ {$key} }}", "{{$key}}"], $stringVal, $rendered);
        }

        // Apply school branding header/footer if requested
        if (!empty($branding['header'])) {
            $rendered = $branding['header'] . "\n\n" . $rendered;
        }

        if (!empty($branding['footer'])) {
            $rendered = $rendered . "\n\n" . $branding['footer'];
        }

        return trim($rendered);
    }

    /**
     * Render both subject and body for a notification template.
     */
    public function renderNotification(
        ?string $subjectTemplate,
        string $bodyTemplate,
        array $variables = [],
        array $branding = []
    ): array {
        $subject = $subjectTemplate ? $this->render($subjectTemplate, $variables, []) : null;
        $body = $this->render($bodyTemplate, $variables, $branding);

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}

