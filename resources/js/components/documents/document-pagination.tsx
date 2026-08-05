import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated, PaginationLink } from '@/types';

const linkClass = (active: boolean, disabled: boolean) =>
    cn(
        buttonVariants({
            variant: active ? 'outline' : 'ghost',
            size: 'icon',
        }),
        disabled && 'pointer-events-none opacity-50',
    );

export function DocumentPagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    // The first and last entries are the previous / next arrows, which we
    // render from the paginator's own urls instead.
    const numbers: PaginationLink[] = page.links.slice(1, -1);

    return (
        <nav
            aria-label="Pagination"
            className="flex items-center justify-between gap-4"
        >
            <p className="text-sm text-muted-foreground">
                {page.from ?? 0}–{page.to ?? 0} of {page.total}
            </p>

            <div className="flex items-center gap-1">
                <PageLink
                    url={page.prev_page_url}
                    label="Previous page"
                    className={linkClass(false, page.prev_page_url === null)}
                >
                    <ChevronLeft />
                </PageLink>

                {numbers.map((link) =>
                    link.url === null ? (
                        <span
                            key={link.label}
                            aria-hidden
                            className="px-2 text-sm text-muted-foreground"
                        >
                            …
                        </span>
                    ) : (
                        <PageLink
                            key={link.label}
                            url={link.url}
                            label={`Page ${link.label}`}
                            current={link.active}
                            className={linkClass(link.active, false)}
                        >
                            {link.label}
                        </PageLink>
                    ),
                )}

                <PageLink
                    url={page.next_page_url}
                    label="Next page"
                    className={linkClass(false, page.next_page_url === null)}
                >
                    <ChevronRight />
                </PageLink>
            </div>
        </nav>
    );
}

function PageLink({
    url,
    label,
    current,
    className,
    children,
}: {
    url: string | null;
    label: string;
    current?: boolean;
    className: string;
    children: React.ReactNode;
}) {
    if (url === null) {
        return (
            <span aria-hidden className={className}>
                {children}
            </span>
        );
    }

    return (
        <Link
            href={url}
            only={['documents']}
            preserveState
            preserveScroll
            aria-label={label}
            aria-current={current ? 'page' : undefined}
            className={className}
        >
            {children}
        </Link>
    );
}
