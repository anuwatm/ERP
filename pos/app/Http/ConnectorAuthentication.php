<?php

namespace Pos\Http;

use Closure;
use Illuminate\Http\Request;

class ConnectorAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        // Read only web-server variables, never a client-supplied identity header.
        $fingerprint = (string) config('pos.connector_fingerprint');
        abort_unless(config('pos.require_mtls') && $fingerprint !== '' && $request->server('SSL_CLIENT_VERIFY') === 'SUCCESS'
            && hash_equals(strtolower($fingerprint), strtolower((string) $request->server('SSL_CLIENT_FINGERPRINT'))), 401, 'Connector authentication required.');
        abort_unless(config('pos.instance_id') && hash_equals((string) config('pos.instance_id'), (string) $request->header('X-POS-Instance')), 403, 'Instance scope mismatch.');
        $scope = match (true) {
            $request->isMethod('PUT') && $request->is('connector/v1/tax-profile') => 'tax:publish',
            $request->isMethod('GET') && $request->is('connector/v1/checkpoint') => 'checkpoint:read',
            $request->isMethod('PUT') && $request->is('connector/v1/checkpoints/*/ack') => 'checkpoint:ack',
            $request->isMethod('PUT') && $request->is('connector/v1/recalls/*') => 'recalls:publish',
            $request->isMethod('PUT') && $request->is('connector/v1/catalog/*') => 'catalog:publish',
            $request->isMethod('PUT') && $request->is('connector/v1/grants/*') => 'grants:manage',
            $request->isMethod('GET') && $request->is('connector/v1/events') => 'events:read',
            $request->isMethod('PUT') && $request->is('connector/v1/results/*') => 'results:write',
            default => null,
        };
        abort_unless($scope && in_array($scope, config('pos.connector_scopes'), true) && count(config('pos.connector_branch_ids')) > 0, 403, 'Connector operation not provisioned.');
        if ($request->has('branch_id')) {
            abort_unless(in_array($request->input('branch_id'), config('pos.connector_branch_ids'), true), 403, 'Branch outside connector scope.');
        }
        abort_if(strlen($request->getContent()) > 2097152, 413, 'Connector body too large.');

        return $next($request);
    }
}
