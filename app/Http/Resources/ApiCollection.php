<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use InvalidArgumentException;

class ApiCollection extends ResourceCollection
{
    /**
     * @var class-string<JsonResource>
     */
    protected string $resourceClass;

    /**
     * @param  mixed  $resource
     * @param  class-string<JsonResource>  $resourceClass
     */
    public function __construct($resource, string $resourceClass)
    {
        parent::__construct($resource);

        if (! is_subclass_of($resourceClass, JsonResource::class)) {
            throw new InvalidArgumentException(sprintf(
                'The resource class [%s] must extend [%s].',
                $resourceClass,
                JsonResource::class
            ));
        }

        $this->resourceClass = $resourceClass;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->isPaginated()) {
            return $this->paginatedResponse($request);
        }

        return [
            'data' => $this->transformItems($this->resource, $request),
        ];
    }

    /**
     * Static helper for cleaner controller usage.
     *
     * @param  mixed  $resource
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function for($resource, string $resourceClass): static
    {
        return new static($resource, $resourceClass);
    }

    protected function isPaginated(): bool
    {
        return $this->resource instanceof LengthAwarePaginator
            || $this->resource instanceof Paginator
            || $this->resource instanceof CursorPaginator;
    }

    /**
     * @return array<string, mixed>
     */
    protected function paginatedResponse(Request $request): array
    {
        if ($this->resource instanceof CursorPaginator) {
            return [
                'data' => $this->transformItems($this->resource->items(), $request),
                'meta' => [
                    'path' => $this->resource->path(),
                    'per_page' => $this->resource->perPage(),
                ],
                'links' => [
                    'next' => $this->resource->nextPageUrl(),
                    'prev' => $this->resource->previousPageUrl(),
                ],
            ];
        }

        if ($this->resource instanceof LengthAwarePaginator) {
            return [
                'data' => $this->transformItems($this->resource->items(), $request),
                'meta' => [
                    'current_page' => $this->resource->currentPage(),
                    'from' => $this->resource->firstItem(),
                    'last_page' => $this->resource->lastPage(),
                    'path' => $this->resource->path(),
                    'per_page' => $this->resource->perPage(),
                    'to' => $this->resource->lastItem(),
                    'total' => $this->resource->total(),
                ],
                'links' => [
                    'first' => $this->resource->url(1),
                    'last' => $this->resource->url($this->resource->lastPage()),
                    'next' => $this->resource->nextPageUrl(),
                    'prev' => $this->resource->previousPageUrl(),
                ],
            ];
        }

        /** @var Paginator $paginator */
        $paginator = $this->resource;

        return [
            'data' => $this->transformItems($paginator->items(), $request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ];
    }

    /**
     * @param  mixed  $items
     * @return array<int, mixed>
     */
    protected function transformItems($items, Request $request): array
    {
        return ($this->resourceClass)::collection(collect($items))->toArray($request);
    }
}