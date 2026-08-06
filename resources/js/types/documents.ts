export type DocumentRole = 'viewer' | 'editor';

export type MembershipStatus = 'pending' | 'accepted' | 'declined';

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
    shared_with_count: number;
    folder: FolderRef | null;
};

export type DocumentResource = {
    uuid: string;
    title: string;
    content_html: string;
    /**
     * The persisted Yjs state, base64 encoded — the document's source of truth.
     * `null` until someone has typed in it.
     */
    state: string | null;
    updated_at_for_humans: string | null;
    folder: FolderRef | null;
};

/** A person on a document — the owner has no membership row, hence `id: null`. */
export type DocumentMember = {
    id: number | null;
    name: string;
    email: string | null;
    role: DocumentRole;
    status: MembershipStatus;
    is_you: boolean;
};

/** A member on the document's presence channel, as `DocumentChannel@join` shapes them. */
export type PresenceUser = {
    id: number;
    name: string;
    role: DocumentRole;
};

/** An invitation the signed-in user has not answered yet. */
export type PendingInvitation = {
    id: number;
    role: DocumentRole;
    invited_at_for_humans: string | null;
    document: { uuid: string; title: string };
    invited_by: { name: string } | null;
};

export type DocumentPermissions = {
    update: boolean;
    delete: boolean;
    file: boolean;
    manageMembers: boolean;
};

/** What the editor renders — derived from the CRDT, kept for search and previews. */
export type EditorSnapshot = {
    content_html: string;
    content_text: string;
};

/** The autosave body: the CRDT plus the snapshot derived from it. */
export type DocumentContent = EditorSnapshot & {
    /** Base64 `Y.encodeStateAsUpdate` of the whole document. */
    state: string;
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
