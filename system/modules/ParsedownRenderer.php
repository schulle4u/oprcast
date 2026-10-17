<?php

declare(strict_types=1);

namespace Oprcast;

require_once dirname(__DIR__) . '/vendor/parsedown/Parsedown.php';

/** Keeps OPRCast heading anchors separate from the unmodified upstream parser. */
final class ParsedownRenderer extends \Parsedown
{
    protected function blockHeader($Line)
    {
        return $this->withHeadingId(parent::blockHeader($Line));
    }

    protected function blockSetextHeader($Line, ?array $Block = null)
    {
        return $this->withHeadingId(parent::blockSetextHeader($Line, $Block));
    }

    private function withHeadingId(?array $block): ?array
    {
        if ($block !== null) {
            $text = $block['element']['handler']['argument'];
            $slug = strtolower(trim(preg_replace('/[^\pL\pN]+/u', '-', $text) ?? '', '-'));
            $block['element']['attributes']['id'] = $slug !== '' ? $slug : 'abschnitt';
        }
        return $block;
    }
}
