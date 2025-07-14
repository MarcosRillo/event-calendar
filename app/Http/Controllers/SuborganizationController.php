<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invitation;
use App\Models\InvitationOrganizationData;
use App\Models\InvitationAdminData;
use Illuminate\Support\Str;

class SuborganizationController extends Controller
{
    public function requestSuborganization(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'organization' => ['required', 'array'],
            'organization.name' => ['required', 'string'],
            'organization.slug' => ['required', 'string', 'unique:invitation_organization_data,slug'],
            'organization.website_url' => ['nullable', 'url'],
            'organization.address' => ['nullable', 'string'],
            'organization.phone' => ['nullable', 'string'],
            'organization.email' => ['nullable', 'email'],
            'admin' => ['required', 'array'],
            'admin.first_name' => ['required', 'string'],
            'admin.last_name' => ['required', 'string'],
            'admin.email' => ['required', 'email'],
            'admin.phone' => ['nullable', 'string'],
        ]);

        $invitation = Invitation::create([
            'email' => $data['email'],
            'token' => Str::random(40),
            'status_id' => 1, // pending
            'created_by' => auth()->id(),
        ]);

        InvitationOrganizationData::create([
            'invitation_id' => $invitation->id,
            'name' => $data['organization']['name'],
            'slug' => $data['organization']['slug'],
            'website_url' => $data['organization']['website_url'],
            'address' => $data['organization']['address'],
            'phone' => $data['organization']['phone'],
            'email' => $data['organization']['email'],
        ]);

        InvitationAdminData::create([
            'invitation_id' => $invitation->id,
            'first_name' => $data['admin']['first_name'],
            'last_name' => $data['admin']['last_name'],
            'email' => $data['admin']['email'],
            'phone' => $data['admin']['phone'],
        ]);

        return response()->json([
            'message' => 'Suborganization request created successfully',
            'invitation' => $invitation,
        ], 201);
    }
}
