<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Auth;

use Illuminate\Http\Request;
use Heygeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;

interface AuthenticatorInterface
{
    /**
     * @throws AuthenticationFailedException
     */
    public function authenticate(Request $request): bool;

    public function getIdentity(Request $request): string;

    public function getTokenId(Request $request): string;
}
