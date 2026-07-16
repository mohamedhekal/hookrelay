<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Http\Controllers;

use Hekal\HookRelay\Exceptions\HookRelayException;
use Hekal\HookRelay\Services\InboundWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class InboundWebhookController extends Controller
{
    public function __invoke(string $source, Request $request, InboundWebhookProcessor $processor): JsonResponse
    {
        try {
            $inbound = $processor->handle($source, $request);
        } catch (HookRelayException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'invalid_signature',
            ], 401);
        }

        return response()->json([
            'id' => $inbound->uuid,
            'source' => $inbound->source,
            'accepted' => true,
        ], 202);
    }
}
