<?php

namespace TuiBackground\Tui;

use Symfony\Component\Tui\Tui;

final class TuiRenderer implements RendererInterface
{
    public function __construct(private readonly Tui $tui)
    {
    }

    public function requestPageRender(): void
    {
        $this->tui->requestRender();
    }
}
