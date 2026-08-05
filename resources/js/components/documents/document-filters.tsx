import { Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type {
    DocumentFilters as Filters,
    DocumentScope,
    DocumentSort,
    SortDirection,
} from '@/types';

const scopes: { value: DocumentScope; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'mine', label: 'Mine' },
    { value: 'shared', label: 'Shared' },
];

/** Sort and direction are one choice to the user, so they share one select. */
const orderings: {
    value: string;
    label: string;
    sort: DocumentSort;
    direction: SortDirection;
}[] = [
    {
        value: 'updated_at:desc',
        label: 'Last edited',
        sort: 'updated_at',
        direction: 'desc',
    },
    {
        value: 'updated_at:asc',
        label: 'Least recently edited',
        sort: 'updated_at',
        direction: 'asc',
    },
    {
        value: 'created_at:desc',
        label: 'Newest',
        sort: 'created_at',
        direction: 'desc',
    },
    {
        value: 'created_at:asc',
        label: 'Oldest',
        sort: 'created_at',
        direction: 'asc',
    },
    { value: 'title:asc', label: 'Title A–Z', sort: 'title', direction: 'asc' },
    {
        value: 'title:desc',
        label: 'Title Z–A',
        sort: 'title',
        direction: 'desc',
    },
];

export function DocumentFilters({
    filters,
    search,
    onSearch,
    onApply,
}: {
    filters: Filters;
    search: string;
    onSearch: (value: string) => void;
    onApply: (changes: Partial<Filters>) => void;
}) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="relative flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <label htmlFor="document-search" className="sr-only">
                    Search documents
                </label>
                <Input
                    id="document-search"
                    type="search"
                    value={search}
                    placeholder="Search titles and content…"
                    onChange={(event) => onSearch(event.target.value)}
                    className="pr-9 pl-9"
                />
                {search !== '' && (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Clear search"
                        onClick={() => onSearch('')}
                        className="absolute top-1/2 right-1 size-7 -translate-y-1/2"
                    >
                        <X />
                    </Button>
                )}
            </div>

            <ToggleGroup
                type="single"
                variant="outline"
                value={filters.scope}
                aria-label="Filter by ownership"
                onValueChange={(value) => {
                    if (value !== '') {
                        onApply({ scope: value as DocumentScope });
                    }
                }}
            >
                {scopes.map((scope) => (
                    <ToggleGroupItem key={scope.value} value={scope.value}>
                        {scope.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            <Select
                value={`${filters.sort}:${filters.direction}`}
                onValueChange={(value) => {
                    const ordering = orderings.find(
                        (option) => option.value === value,
                    );

                    if (ordering) {
                        onApply({
                            sort: ordering.sort,
                            direction: ordering.direction,
                        });
                    }
                }}
            >
                <SelectTrigger className="sm:w-52" aria-label="Sort documents">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {orderings.map((ordering) => (
                        <SelectItem key={ordering.value} value={ordering.value}>
                            {ordering.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
