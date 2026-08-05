export type DocumentRole = 'viewer' | 'editor';

export type DocumentListItem = {
    uuid: string;
    title: string;
    excerpt: string;
    updated_at_for_humans: string | null;
    owner: { name: string };
    is_owner: boolean;
    role: DocumentRole | null;
};

export type DocumentResource = {
    uuid: string;
    title: string;
    content_html: string;
    updated_at_for_humans: string | null;
};

export type DocumentPermissions = {
    update: boolean;
    delete: boolean;
};

export type DocumentContent = {
    content_html: string;
    content_text: string;
};

export type SaveStatus = 'idle' | 'unsaved' | 'saving' | 'saved' | 'error';
