<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\CertificateTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CertificateTemplateController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = CertificateTemplate::query();

        // Standard user can only list active templates
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            $query->where('is_active', true);
        }

        if ($request->has('certificate_type')) {
            $query->where('certificate_type', $request->input('certificate_type'));
        }

        if ($request->has('language')) {
            $query->where('language', $request->input('language'));
        }

        $templates = $query->orderBy('name')->get();

        return $this->successResponse($templates, 'Certificate templates retrieved successfully');
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to manage certificate templates', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'name' => 'required|string|max:255',
            'certificate_type' => 'required|in:membership,baptism,marriage,death,recommendation,no_objection,course_completion,custom',
            'language' => 'required|in:en,ml,de',
            'html_template' => 'required|string',
            'background_image_path' => 'nullable|string|max:255',
            'seal_required' => 'boolean',
            'signature_required' => 'boolean',
            'default_priest_signature_position' => 'nullable|string|max:100',
            'default_seal_position' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $data = $request->all();
        $data['created_by'] = $request->user()->id;

        $template = CertificateTemplate::create($data);

        return $this->successResponse($template, 'Certificate template created successfully', 201);
    }

    public function show(Request $request, $id)
    {
        $template = CertificateTemplate::findOrFail($id);

        if (!$template->is_active && !$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Template is inactive', 403);
        }

        return $this->successResponse($template, 'Certificate template retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to manage certificate templates', 403);
        }

        $template = CertificateTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'html_template' => 'string',
            'background_image_path' => 'nullable|string|max:255',
            'seal_required' => 'boolean',
            'signature_required' => 'boolean',
            'default_priest_signature_position' => 'nullable|string|max:100',
            'default_seal_position' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $data = $request->all();
        $data['updated_by'] = $request->user()->id;

        $template->update($data);

        return $this->successResponse($template, 'Certificate template updated successfully');
    }

    public function activate(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $template = CertificateTemplate::findOrFail($id);
        $template->update(['is_active' => true, 'updated_by' => $request->user()->id]);

        return $this->successResponse($template, 'Certificate template activated successfully');
    }

    public function deactivate(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $template = CertificateTemplate::findOrFail($id);
        $template->update(['is_active' => false, 'updated_by' => $request->user()->id]);

        return $this->successResponse($template, 'Certificate template deactivated successfully');
    }
}
