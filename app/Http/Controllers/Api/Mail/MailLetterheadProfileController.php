<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\StructureType;
use App\Models\StructureTypeDocumentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailLetterheadProfileController extends Controller
{
    public function index(): JsonResponse
    {
        $types = StructureType::query()
            ->with('documentProfile')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($types);
    }

    public function update(Request $request, StructureType $structureType): JsonResponse
    {
        $data = $request->validate([
            'ministry_line' => ['nullable', 'string', 'max:255'],
            'header_title' => ['nullable', 'string', 'max:255'],
            'append_structure_name' => ['nullable', 'boolean'],
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:255'],
            'republic_line' => ['nullable', 'string', 'max:255'],
            'motto' => ['nullable', 'string', 'max:255'],
            'signatory_left_label' => ['nullable', 'string', 'max:255'],
            'signatory_right_label' => ['nullable', 'string', 'max:255'],
        ]);

        $data['append_structure_name'] = (bool) ($data['append_structure_name'] ?? true);

        $profile = StructureTypeDocumentProfile::query()->updateOrCreate(
            ['structure_type_id' => $structureType->id],
            $data,
        );

        return response()->json($structureType->fresh()->load('documentProfile'));
    }
}
