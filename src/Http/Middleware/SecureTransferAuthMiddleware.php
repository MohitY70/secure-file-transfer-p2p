<?php

namespace HeyGeeks\SecureFileTransfer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use HeyGeeks\SecureFileTransfer\SecureFileTransfer;
use HeyGeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;
use HeyGeeks\SecureFileTransfer\Exceptions\ReplayAttackException;
use HeyGeeks\SecureFileTransfer\Exceptions\TransferLimitExceededException;

class SecureTransferAuthMiddleware
{
    public function __construct(protected SecureFileTransfer $transfer)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            // Authenticate the request
            $this->transfer->getAuthenticator()->authenticate($request);

            // Get token ID for rate limiting and nonce validation
            $tokenId = $this->transfer->getAuthenticator()->getTokenId($request);
            $clientId = $this->transfer->getAuthenticator()->getIdentity($request);

            // Check for nonce (replay protection)
            $nonce = $request->header('X-Nonce');
            if ($nonce) {
                if ($this->transfer->getNonceStore()->exists($nonce)) {
                    $this->transfer->getLogger()->replayDetected($nonce, $clientId);
                    throw new ReplayAttackException('Nonce has already been used (replay attack detected)');
                }
                // Record nonce as used
                $this->transfer->getNonceStore()->record($nonce, 60);
            }

            // Check rate limit
            $this->transfer->getRateLimiter()->isAllowed($tokenId);

            // Store authentication info in request for use in controllers
            $request->attributes->set('secure_transfer.client_id', $clientId);
            $request->attributes->set('secure_transfer.token_id', $tokenId);

            return $next($request);
        } catch (AuthenticationFailedException $e) {
            $this->transfer->getLogger()->authenticationFailed($e->getMessage());
            return response()->json(['error' => 'Authentication failed'], 401);
        } catch (ReplayAttackException $e) {
            return response()->json(['error' => 'Replay attack detected'], 403);
        } catch (TransferLimitExceededException $e) {
            $clientId = $request->header('X-Client-ID') ?? 'unknown';
            $this->transfer->getLogger()->rateLimitHit($clientId);
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
