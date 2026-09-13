<?php

namespace App\Services;

use App\Models\ClassificationNode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentClassificationService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function tree(?int $structureId = null, bool $activeOnly = true): array
    {
        $query = ClassificationNode::query()
            ->with('structure:id,code,name')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($structureId) {
            $query->where(function ($q) use ($structureId) {
                $q->whereNull('structure_id')->orWhere('structure_id', $structureId);
            });
        }

        $nodes = $query->get();

        return $this->buildTree($nodes->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ClassificationNode
    {
        return DB::transaction(function () use ($data, $actor) {
            $parent = null;
            if (! empty($data['parent_id'])) {
                $parent = ClassificationNode::query()->findOrFail($data['parent_id']);
            }

            $node = ClassificationNode::query()->create([
                'parent_id' => $parent?->id,
                'structure_id' => $data['structure_id'] ?? $parent?->structure_id,
                'code' => $data['code'],
                'name' => $data['name'],
                'depth' => $parent ? $parent->depth + 1 : 0,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $node->path = $this->computePath($node);
            $node->save();

            $this->audit->log('classification_node.created', $node, ['actor_id' => $actor->id]);

            return $node->fresh('structure');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClassificationNode $node, array $data, User $actor): ClassificationNode
    {
        return DB::transaction(function () use ($node, $data, $actor) {
            if (isset($data['parent_id']) && (int) $data['parent_id'] === (int) $node->id) {
                throw new InvalidArgumentException('Un nœud ne peut pas être son propre parent.');
            }

            if (array_key_exists('parent_id', $data)) {
                $parent = $data['parent_id']
                    ? ClassificationNode::query()->findOrFail($data['parent_id'])
                    : null;
                $node->parent_id = $parent?->id;
                $node->depth = $parent ? $parent->depth + 1 : 0;
            }

            foreach (['code', 'name', 'structure_id', 'is_active', 'sort_order'] as $field) {
                if (array_key_exists($field, $data)) {
                    $node->{$field} = $data[$field];
                }
            }

            $node->path = $this->computePath($node);
            $node->save();
            $this->refreshDescendantPaths($node);

            $this->audit->log('classification_node.updated', $node, ['actor_id' => $actor->id]);

            return $node->fresh('structure');
        });
    }

    public function delete(ClassificationNode $node, User $actor): void
    {
        if ($node->children()->exists()) {
            throw new InvalidArgumentException('Supprimer d’abord les sous-nœuds.');
        }

        if ($node->documents()->exists()) {
            throw new InvalidArgumentException('Des documents sont encore classés sous ce nœud.');
        }

        $this->audit->log('classification_node.deleted', $node, ['actor_id' => $actor->id]);
        $node->delete();
    }

    private function computePath(ClassificationNode $node): string
    {
        $segments = [$node->code];
        $current = $node;
        while ($current->parent_id) {
            $current = ClassificationNode::query()->find($current->parent_id);
            if (! $current) {
                break;
            }
            array_unshift($segments, $current->code);
        }

        return implode('/', $segments);
    }

    private function refreshDescendantPaths(ClassificationNode $node): void
    {
        foreach ($node->children as $child) {
            $child->depth = $node->depth + 1;
            $child->path = $this->computePath($child);
            $child->save();
            $this->refreshDescendantPaths($child);
        }
    }

    /**
     * @param  list<ClassificationNode>  $nodes
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $nodes, ?int $parentId = null): array
    {
        $branch = [];
        foreach ($nodes as $node) {
            if ((int) $node->parent_id !== (int) $parentId) {
                continue;
            }
            $branch[] = [
                'id' => $node->id,
                'parent_id' => $node->parent_id,
                'structure_id' => $node->structure_id,
                'structure' => $node->structure,
                'code' => $node->code,
                'name' => $node->name,
                'path' => $node->path,
                'depth' => $node->depth,
                'is_active' => $node->is_active,
                'sort_order' => $node->sort_order,
                'children' => $this->buildTree($nodes, $node->id),
            ];
        }

        return $branch;
    }
}
