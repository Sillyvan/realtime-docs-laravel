export type DocumentRole = 'viewer' | 'editor';

export type FolderRef = {
    uuid: string;
    name: string;
};

export type Folder = FolderRef & {
    documents_count: number;
};

export type DocumentListItem = {
    uuid: string;
    title: string;
    excerpt: string;
    updated_at_for_humans: string | null;
    owner: { name: string };
    is_owner: boolean;
    role: DocumentRole | null;
    folder: FolderRef | null;
};

export type DocumentResource = {
    uuid: string;
    title: string;
    content_html: string;
    updated_at_for_humans: string | null;
    folder: FolderRef | null;
};

export type DocumentPermissions = {
    update: boolean;
    delete: boolean;
    file: boolean;
};

export type DocumentContent = {
    content_html: string;
    content_text: string;
};

export type SaveStatus = 'idle' | 'unsaved' | 'saving' | 'saved' | 'error';

export type DocumentScope = 'all' | 'mine' | 'shared';

export type DocumentSort = 'updated_at' | 'created_at' | 'title';

export type SortDirection = 'asc' | 'desc';

export type DocumentFilters = {
    scope: DocumentScope;
    /** A folder uuid, the `root` keyword, or null for every folder. */
    folder: string | null;
    q: string | null;
    sort: DocumentSort;
    direction: SortDirection;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    page: number | null;
    active: boolean;
};

/** The shape Laravel's LengthAwarePaginator serialises to. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
