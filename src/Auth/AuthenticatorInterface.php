<?php

namespace HeyGeeks\SecureFileTransfer\Auth;

use Illuminate\Http\Request;
use HeyGeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;

interface AuthenticatorInterface
{
    /**
     * Authenticate the request.
     *
     * @param Request $request
     * @return bool
     * @throws AuthenticationFailedException
     */
    public function authenticate(Request $request): bool;

    /**
     * Get the client identity from the request.
     *
     * @param Request $request
     * @return string
     */
    public function getIdentity(Request $request): string;

    /**
     * Get the token ID from the request.
     *
     * @param Request $request
     * @return string
     */
    public function getTokenId(Request $request): string;
}
