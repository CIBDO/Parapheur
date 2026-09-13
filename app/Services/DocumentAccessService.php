<?php

namespace App\Services;

use App\Enums\DocumentAccessAbility;
use App\Enums\DocumentArchiveStatus;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentAccessRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DocumentAccessService
{
    public function __construct(
        private readonly DelegationResolver $delegations,
    ) {}

    public function canAccess(User $user, Document $document): bool
    {
        if ($this->isCircuitParty($user, $document)) {
            return true;
        }

        if ($this->hasExplicitAcl($user, $document, DocumentAccessAbility::View)) {
            return true;
        }

        if ($this->hasWorkspaceAccess($user, $document)) {
            return true;
        }

        return $this->hasViewClearance($user, $document);
    }

    public function canDownload(User $user, Document $document): bool
    {
        if (! $this->canAccess($user, $document)) {
            return false;
        }

        if ($user->can('ged.download') || $user->can('admin.access') || $user->can('documents.act')) {
            return true;
        }

        return $this->hasExplicitAcl($user, $document, DocumentAccessAbility::Download)
            || $this->isCircuitParty($user, $document)
            || $this->hasWorkspaceAccess($user, $document);
    }

    public function canEdit(User $user, Document $document): bool
    {
        if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
            return false;
        }

        if ($document->archive_status === DocumentArchiveStatus::Gele) {
            return false;
        }

        if ($user->can('admin.access')) {
            return true;
        }

        if ($this->hasExplicitAcl($user, $document, DocumentAccessAbility::Edit)) {
            return true;
        }

        return $this->canProcess($user, $document, 'act')
            || ((int) $document->author_id === (int) $user->id
                && in_array($document->status?->value ?? (string) $document->status, ['brouillon', 'depose', 'a_corriger', 'corrige'], true));
    }

    public function canShare(User $user, Document $document): bool
    {
        if (! $this->canAccess($user, $document)) {
            return false;
        }

        if ($user->can('ged.share') || $user->can('admin.access')) {
            return true;
        }

        return $this->hasExplicitAcl($user, $document, DocumentAccessAbility::Share)
            || (int) $document->author_id === (int) $user->id;
    }

    /**
     * Destinataire autorisé à recevoir le dossier selon le niveau de classification.
     */
    public function canReceive(User $recipient, Document $document): bool
    {
        $confidentiality = $this->resolveConfidentiality($document);

        return match ($confidentiality) {
            DocumentConfidentiality::TresConfidentiel => $recipient->can('admin.access')
                || $recipient->can('dashboard.dg')
                || $recipient->can('documents.validate'),
            DocumentConfidentiality::Confidentiel => $recipient->can('admin.access')
                || $recipient->can('dashboard.dg')
                || $recipient->can('documents.validate')
                || $recipient->can('documents.vise'),
            default => $recipient->can('documents.act')
                || $recipient->can('documents.create')
                || $recipient->can('admin.access')
                || $recipient->can('ged.view'),
        };
    }

    /**
     * Actions de traitement (viser, valider, retourner…) : destinataire courant,
     * délégataire actif, ou administrateur.
     */
    public function canProcess(User $user, Document $document, string $action = 'act'): bool
    {
        if ($user->can('admin.access')) {
            return true;
        }

        if ((int) $document->current_assignee_id === (int) $user->id) {
            return true;
        }

        // Destinataire parallèle (transmission libre multi-destinataires).
        if ($document->transmissions()
            ->where('to_user_id', $user->id)
            ->whereIn('status', ['pending', 'seen'])
            ->exists()) {
            return true;
        }

        $delegator = $this->delegations->resolveDelegator($user, $document, $action);

        return $delegator !== null
            && (int) $delegator->id === (int) $document->current_assignee_id;
    }

    /**
     * Transmission : destinataire courant, ou auteur tant que le dossier n’est pas figé
     * chez un tiers (brouillon / correction).
     */
    public function canTransmit(User $user, Document $document): bool
    {
        if ($this->canProcess($user, $document, 'act')) {
            return true;
        }

        if ((int) $document->author_id !== (int) $user->id) {
            return false;
        }

        $status = $document->status?->value ?? (string) $document->status;

        return in_array($status, ['brouillon', 'depose', 'a_corriger', 'corrige'], true);
    }

    public function authorize(User $user, Document $document): void
    {
        abort_unless($this->canAccess($user, $document), 403, 'Accès au document refusé.');
    }

    public function authorizeReceive(User $recipient, Document $document): void
    {
        abort_unless(
            $this->canReceive($recipient, $document),
            403,
            'Le destinataire n’a pas le niveau d’habilitation requis pour ce document.'
        );
    }

    public function authorizeProcess(User $user, Document $document, string $action = 'act'): void
    {
        $this->authorize($user, $document);
        abort_unless(
            $this->canProcess($user, $document, $action),
            403,
            'Action réservée au destinataire actuel du dossier.'
        );
    }

    public function authorizeTransmit(User $user, Document $document): void
    {
        $this->authorize($user, $document);
        abort_unless(
            $this->canTransmit($user, $document),
            403,
            'Transmission réservée au destinataire actuel (ou à l’auteur avant prise en charge).'
        );
    }

    public function authorizeDownload(User $user, Document $document): void
    {
        abort_unless($this->canDownload($user, $document), 403, 'Téléchargement refusé.');
        abort_if(
            $document->antivirus_status === 'infected',
            422,
            'Fichier identifié comme dangereux : accès refusé.'
        );
    }

    /**
     * Partie prenante du circuit (need-to-know) : auteur, destinataire courant, historique de transmission.
     */
    public function isCircuitParty(User $user, Document $document): bool
    {
        if ((int) $document->author_id === (int) $user->id) {
            return true;
        }

        if ((int) $document->current_assignee_id === (int) $user->id) {
            return true;
        }

        return $document->transmissions()
            ->where(function ($query) use ($user) {
                $query->where('to_user_id', $user->id)
                    ->orWhere('from_user_id', $user->id);
            })
            ->exists();
    }

    /**
     * Accès via espace de travail (membre) ou partage workspace explicite.
     */
    public function hasWorkspaceAccess(User $user, Document $document): bool
    {
        if ($document->workspaceLinks()
            ->whereHas('workspace.members', fn (Builder $m) => $m->where('user_id', $user->id))
            ->exists()) {
            return true;
        }

        return $document->workspaceLinks()
            ->whereHas('workspace.shares', function (Builder $s) use ($user, $document) {
                $s->where('grantee_user_id', $user->id)
                    ->where(function (Builder $scope) use ($document) {
                        $scope->where('document_id', $document->id)
                            ->orWhereNull('document_id');
                    })
                    ->where(function (Builder $exp) {
                        $exp->whereNull('valid_from')->orWhere('valid_from', '<=', now());
                    })
                    ->where(function (Builder $exp) {
                        $exp->whereNull('valid_until')->orWhere('valid_until', '>', now());
                    });
            })
            ->exists()
            || \App\Models\WorkspaceShare::query()
                ->where('document_id', $document->id)
                ->where('grantee_user_id', $user->id)
                ->where(function (Builder $exp) {
                    $exp->whereNull('valid_from')->orWhere('valid_from', '<=', now());
                })
                ->where(function (Builder $exp) {
                    $exp->whereNull('valid_until')->orWhere('valid_until', '>', now());
                })
                ->exists();
    }

    /**
     * Scope de sécurité intégré à la requête (jamais filtrer après coup).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('admin.access')) {
            return $query;
        }

        $workspaceOrigins = [
            DocumentOrigin::Personal->value,
            DocumentOrigin::Workspace->value,
        ];

        return $query->where(function (Builder $q) use ($user, $workspaceOrigins) {
            // Circuit need-to-know
            $q->where('author_id', $user->id)
                ->orWhere('current_assignee_id', $user->id)
                ->orWhereHas('transmissions', function (Builder $t) use ($user) {
                    $t->where('to_user_id', $user->id)->orWhere('from_user_id', $user->id);
                })
                // ACL explicites
                ->orWhereHas('accessRules', function (Builder $r) use ($user) {
                    $r->where(function (Builder $rule) use ($user) {
                        $rule->where('user_id', $user->id)
                            ->orWhere(function (Builder $s) use ($user) {
                                if ($user->structure_id) {
                                    $s->where('structure_id', $user->structure_id);
                                }
                            })
                            ->orWhere(function (Builder $roleQ) use ($user) {
                                $roleNames = $user->getRoleNames()->all();
                                if ($roleNames !== []) {
                                    $roleQ->whereIn('role_name', $roleNames);
                                }
                            });
                    })
                        ->where(function (Builder $exp) {
                            $exp->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->where('ability', DocumentAccessAbility::View->value);
                })
                // Membre d’un workspace lié au document
                ->orWhereHas('workspaceLinks.workspace.members', function (Builder $m) use ($user) {
                    $m->where('user_id', $user->id);
                })
                // Partage workspace explicite
                ->orWhereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('workspace_shares')
                        ->whereColumn('workspace_shares.document_id', 'documents.id')
                        ->where('workspace_shares.grantee_user_id', $user->id)
                        ->where(function ($exp) {
                            $exp->whereNull('workspace_shares.valid_from')
                                ->orWhere('workspace_shares.valid_from', '<=', now());
                        })
                        ->where(function ($exp) {
                            $exp->whereNull('workspace_shares.valid_until')
                                ->orWhere('workspace_shares.valid_until', '>', now());
                        });
                });

            // Clearance hors circuit : JAMAIS pour origines personal/workspace
            if ($user->can('dashboard.dg')) {
                $q->orWhere(function (Builder $dg) use ($workspaceOrigins) {
                    $dg->whereNotIn('origin', $workspaceOrigins)
                        ->whereIn('confidentiality', [
                            DocumentConfidentiality::Normal->value,
                            DocumentConfidentiality::Restreint->value,
                            DocumentConfidentiality::Confidentiel->value,
                        ]);
                });
            } elseif ($user->can('documents.act') || $user->can('documents.create') || $user->can('ged.view')) {
                // Restreint : même structure uniquement (hors personal/workspace)
                if ($user->structure_id) {
                    $q->orWhere(function (Builder $rest) use ($user, $workspaceOrigins) {
                        $rest->whereNotIn('origin', $workspaceOrigins)
                            ->where('confidentiality', DocumentConfidentiality::Restreint->value)
                            ->where('structure_id', $user->structure_id);
                    });
                }

                // Documents GED « normal » : auteur, structure émettrice ou propriétaire
                $q->orWhere(function (Builder $ged) use ($user) {
                    $ged->where('origin', DocumentOrigin::Ged->value)
                        ->where('confidentiality', DocumentConfidentiality::Normal->value)
                        ->where(function (Builder $own) use ($user) {
                            $own->where('author_id', $user->id);
                            if ($user->structure_id) {
                                $own->orWhere('structure_id', $user->structure_id)
                                    ->orWhere('owner_structure_id', $user->structure_id);
                            }
                        });
                });
            }
        });
    }

    public function hasExplicitAcl(User $user, Document $document, DocumentAccessAbility $ability): bool
    {
        $roleNames = $user->getRoleNames()->all();

        return DocumentAccessRule::query()
            ->where('document_id', $document->id)
            ->where('ability', $ability->value)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $q) use ($user, $roleNames) {
                $q->where('user_id', $user->id);
                if ($user->structure_id) {
                    $q->orWhere('structure_id', $user->structure_id);
                }
                if ($roleNames !== []) {
                    $q->orWhereIn('role_name', $roleNames);
                }
            })
            ->exists();
    }

    /**
     * Accès hors circuit selon la classification (aligné réunions / agenda).
     * Les documents personal/workspace n’utilisent JAMAIS la clearance structure GED.
     */
    private function hasViewClearance(User $user, Document $document): bool
    {
        $origin = $document->origin instanceof DocumentOrigin
            ? $document->origin
            : DocumentOrigin::tryFrom((string) $document->origin);

        if ($origin?->isWorkspaceBound()) {
            return false;
        }

        $confidentiality = $this->resolveConfidentiality($document);

        if ($confidentiality === DocumentConfidentiality::TresConfidentiel) {
            return $user->can('admin.access');
        }

        if ($confidentiality === DocumentConfidentiality::Confidentiel) {
            return $user->can('admin.access') || $user->can('dashboard.dg');
        }

        if ($confidentiality === DocumentConfidentiality::Restreint) {
            if ($user->can('admin.access') || $user->can('dashboard.dg')) {
                return true;
            }

            return (int) $user->structure_id === (int) $document->structure_id
                && ($user->can('documents.act') || $user->can('documents.create') || $user->can('ged.view'));
        }

        // Normal : DG / admin ; hors circuit GED : même structure avec ged.view/documents.*
        if ($user->can('admin.access') || $user->can('dashboard.dg')) {
            return true;
        }

        if ($document->origin === DocumentOrigin::Ged
            && ($user->can('ged.view') || $user->can('documents.act') || $user->can('documents.create'))) {
            return (int) $user->structure_id === (int) $document->structure_id
                || (int) $user->structure_id === (int) $document->owner_structure_id
                || (int) $document->author_id === (int) $user->id;
        }

        return false;
    }

    private function resolveConfidentiality(Document $document): DocumentConfidentiality
    {
        if ($document->confidentiality instanceof DocumentConfidentiality) {
            return $document->confidentiality;
        }

        return DocumentConfidentiality::tryFrom((string) $document->confidentiality)
            ?? DocumentConfidentiality::Normal;
    }
}
