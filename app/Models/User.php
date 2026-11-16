<?php

namespace App\Models;

use App\Support\Rbac\AbilityMapper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'title',
        'phone',
        'email',
        'password',
        'is_active',
        'must_change_password',
        'structure_id',
        'position_title',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function structureAssignments(): HasMany
    {
        return $this->hasMany(UserStructureAssignment::class);
    }

    public function organizationalRoleAssignments(): HasMany
    {
        return $this->hasMany(OrganizationalRoleAssignment::class);
    }

    public function workContext(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserWorkContext::class);
    }

    public function announcementAcknowledgements(): HasMany
    {
        return $this->hasMany(AnnouncementAcknowledgement::class);
    }

    public function authoredDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'author_id');
    }

    public function transmissionsReceived(): HasMany
    {
        return $this->hasMany(DocumentTransmission::class, 'to_user_id');
    }

    public function meetingParticipations(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function abilityRules(): array
    {
        return app(AbilityMapper::class)->forUser($this);
    }

    /**
     * @param  string|list<string>  $codes
     */
    public function hasRoleWithCode(string|array $codes): bool
    {
        $codes = (array) $codes;

        return $this->roles->contains(
            fn ($role) => in_array($role->code, $codes, true)
        );
    }

    public function primaryRole(): ?Role
    {
        /** @var Role|null $role */
        $role = $this->roles()->first();

        return $role;
    }

    public function homeRoute(): string
    {
        return $this->primaryRole()?->home_route ?: '/parapheur';
    }
}
