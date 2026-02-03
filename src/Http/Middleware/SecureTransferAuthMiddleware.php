<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Heygeeks\SecureFileTransfer\Auth\AuthenticatorInterface;
use Heygeeks\SecureFileTransfer\Auth\HmacAuthenticator;
use Heygeeks\SecureFileTransfer\Auth\JwtAuthenticator;
use Heygeeks\SecureFileTransfer\Auth\TokenAuthenticator;
use Heygeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;
use Heygeeks\SecureFileTransfer\Exceptions\ReplayAttackException;
use Heygeeks\SecureFileTransfer\Exceptions\TransferLimitExceededException;
use Heygeeks\SecureFileTransfer\Logging\TransferLogger;
use Heygeeks\SecureFileTransfer\Security\NonceStore;
use Heygeeks\SecureFileTransfer\Security\RateLimiter;

class SecureTransferAuthMiddleware
{
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly NonceStore $nonceStore,
        private readonly TransferLogger $logger,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $authenticator = $this->resolveAuthenticator();
            $authenticator->authenticate($request);

            $tokenId = $authenticator->getTokenId($request);
            $request->attributes->set('tokenId', $tokenId);

            $this->rateLimiter->hit($tokenId);
        } catch (ReplayAttackException $exception) {
            $this->logger->logReplayAttack($request->header('X-Nonce', ''), (string) $request->ip());

            return response()->json([
                'error' => 'Authentication failed',
                'message' => $exception->getMessage(),
            ], 401);
        } catch (TransferLimitExceededException $exception) {
            $this->logger->log(TransferLogger::RATE_LIMIT_HIT, [
                'tokenId' => (string) $request->attributes->get('tokenId'),
                'ip' => $request->ip(),
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => $exception->getMessage(),
            ], 429);
        } catch (AuthenticationFailedException $exception) {
            $this->logger->logAuthFailure((string) $request->ip(), $exception->getMessage());

            return response()->json([
                'error' => 'Authentication failed',
                'message' => $exception->getMessage(),
            ], 401);
        }

        return $next($request);
    }

    private function resolveAuthenticator(): AuthenticatorInterface
    {
        $strategy = (string) config('secure-transfer.auth.strategy', 'bearer');

        return match ($strategy) {
            'hmac' => new HmacAuthenticator($this->nonceStore),
            'jwt' => new JwtAuthenticator(),
            default => new TokenAuthenticator(),
        };
    }
}
