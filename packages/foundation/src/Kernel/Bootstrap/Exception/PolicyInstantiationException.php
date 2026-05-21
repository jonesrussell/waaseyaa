<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap\Exception;

/**
 * Thrown when AccessPolicyRegistry cannot instantiate a #[PolicyAttribute] class.
 *
 * This is a boot-time fatal error. Kernel boot fails immediately; no silent logging.
 *
 * @api
 */
final class PolicyInstantiationException extends \RuntimeException {}
