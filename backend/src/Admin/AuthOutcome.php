<?php

declare(strict_types=1);

namespace FormularioDorado\Admin;

enum AuthOutcome
{
    case Success;
    case Invalid;
    case Throttled;
}
