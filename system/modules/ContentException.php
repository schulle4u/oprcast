<?php

declare(strict_types=1);

namespace Oprcast;

use RuntimeException;

/** Signals invalid, unsafe or missing content encountered while serving the site. */
class ContentException extends RuntimeException
{
}

