<?php

namespace App\Http\Controllers;

use App\Support\ProjectContext;
use Illuminate\Http\Request;

class ProjectSwitchController extends Controller
{
    public function __invoke(Request $request, ProjectContext $context)
    {
        $value = $request->input('project_id');
        $context->set($value === 'all' || $value === '' || $value === null ? null : (int) $value);

        // Go back to the same page (keeping its filters), but start again at page 1.
        $previous = url()->previous(route('dashboard'));
        $parts = parse_url($previous);
        parse_str($parts['query'] ?? '', $query);
        unset($query['page']);

        return redirect()->to(strtok($previous, '?').($query ? '?'.http_build_query($query) : ''));
    }
}
