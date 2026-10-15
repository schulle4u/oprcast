<?php

declare(strict_types=1);

namespace Oprcast;

/** Signals a requested resource that does not exist or is not published. */
final class NotFoundException extends ContentException
{
}
