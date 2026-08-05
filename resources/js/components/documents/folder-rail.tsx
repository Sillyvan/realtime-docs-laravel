import { Form } from '@inertiajs/react';
import {
    Files,
    FolderPlus,
    Folder as FolderIcon,
    Inbox,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import FolderController from '@/actions/App/Http/Controllers/FolderController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { DocumentFilters, Folder } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

export function FolderRail({
    folders,
    filters,
    onSelect,
}: {
    folders: Folder[];
    filters: DocumentFilters;
    onSelect: (folder: string | null) => void;
}) {
    // Folders file the documents you own, so they can't narrow shared ones.
    const applies = filters.scope !== 'shared';

    return (
        <nav
            aria-label="Folders"
            className="flex w-full shrink-0 flex-col gap-1 sm:w-56"
        >
            <div className="flex items-center justify-between gap-2 px-2 pb-1">
                <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Folders
                </h2>
                <NewFolderDialog />
            </div>

            <div
                className={cn(
                    'flex flex-col gap-1',
                    !applies && 'pointer-events-none opacity-50',
                )}
                aria-hidden={!applies}
            >
                <RailButton
                    icon={<Files />}
                    label="All documents"
                    active={filters.folder === null}
                    onSelect={() => onSelect(null)}
                />

                <RailButton
                    icon={<Inbox />}
                    label="Unfiled"
                    active={filters.folder === 'root'}
                    onSelect={() => onSelect('root')}
                />

                {folders.map((folder) => (
                    <RailButton
                        key={folder.uuid}
                        icon={<FolderIcon />}
                        label={folder.name}
                        count={folder.documents_count}
                        active={filters.folder === folder.uuid}
                        onSelect={() => onSelect(folder.uuid)}
                        action={<FolderMenu folder={folder} />}
                    />
                ))}
            </div>

            {!applies && (
                <p className="px-2 pt-1 text-xs text-muted-foreground">
                    Folders only apply to documents you own.
                </p>
            )}
        </nav>
    );
}

function RailButton({
    icon,
    label,
    count,
    active,
    onSelect,
    action,
}: {
    icon: React.ReactNode;
    label: string;
    count?: number;
    active: boolean;
    onSelect: () => void;
    action?: React.ReactNode;
}) {
    return (
        <div className="group/rail flex items-center gap-1">
            <button
                type="button"
                onClick={onSelect}
                aria-current={active ? 'true' : undefined}
                className={cn(
                    'flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent/50 [&_svg]:size-4 [&_svg]:shrink-0 [&_svg]:text-muted-foreground',
                    active && 'bg-accent font-medium',
                )}
            >
                {icon}
                <span className="truncate">{label}</span>
                {count !== undefined && (
                    <span className="ml-auto text-xs text-muted-foreground">
                        {count}
                    </span>
                )}
            </button>

            {action}
        </div>
    );
}

function FolderMenu({ folder }: { folder: Folder }) {
    const [renaming, setRenaming] = useState(false);
    const [deleting, setDeleting] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${folder.name}`}
                        className="size-7 shrink-0 opacity-0 group-hover/rail:opacity-100 focus-visible:opacity-100"
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setRenaming(true)}>
                        <Pencil />
                        Rename
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setDeleting(true)}
                    >
                        <Trash2 />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <FolderNameDialog
                open={renaming}
                onOpenChange={setRenaming}
                title="Rename folder"
                submitLabel="Save"
                defaultValue={folder.name}
                form={FolderController.update.form(folder.uuid)}
            />

            <Dialog open={deleting} onOpenChange={setDeleting}>
                <DialogContent>
                    <DialogTitle>Delete this folder?</DialogTitle>
                    <DialogDescription>
                        “{folder.name}” will be removed. The documents in it are
                        kept and move back to Unfiled.
                    </DialogDescription>

                    <Form
                        {...FolderController.destroy.form(folder.uuid)}
                        onSuccess={() => setDeleting(false)}
                    >
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete folder
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function NewFolderDialog() {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                aria-label="New folder"
                className="size-7"
                onClick={() => setOpen(true)}
            >
                <FolderPlus />
            </Button>

            <FolderNameDialog
                open={open}
                onOpenChange={setOpen}
                title="New folder"
                submitLabel="Create folder"
                form={FolderController.store.form()}
            />
        </>
    );
}

function FolderNameDialog({
    open,
    onOpenChange,
    title,
    submitLabel,
    defaultValue,
    form,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    submitLabel: string;
    defaultValue?: string;
    form: RouteFormDefinition<'post'>;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription className="sr-only">
                    Folders keep the documents you own organised.
                </DialogDescription>

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="folder-name">Name</Label>
                                <Input
                                    id="folder-name"
                                    name="name"
                                    // Re-seeds the field each time the dialog opens.
                                    key={String(open)}
                                    defaultValue={defaultValue}
                                    placeholder="Project notes"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button type="submit" disabled={processing}>
                                    {submitLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
