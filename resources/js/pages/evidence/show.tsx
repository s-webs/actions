import { Head, Link, router } from '@inertiajs/react';
import { File, Link2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { evidenceLabel } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';

interface EvidenceFile {
    id: number;
    title: string | null;
    type: 'file' | 'link';
    url: string;
    stage_title: string | null;
    period: string | null;
    uploaded_at: string;
}

interface EvidenceGroup {
    date: string;
    label: string;
    files: EvidenceFile[];
}

interface EvidenceShowProps {
    measure: { id: number; number: number; title: string };
    groups: EvidenceGroup[];
    canDelete: boolean;
}

const IMAGE_EXTENSIONS = new Set(['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff', 'heic', 'heif']);

const FILE_KIND: Record<string, { label: string; className: string }> = {
    doc: { label: 'DOC', className: 'bg-[#2B579A] text-white' },
    docx: { label: 'DOC', className: 'bg-[#2B579A] text-white' },
    xls: { label: 'XLS', className: 'bg-[#217346] text-white' },
    xlsx: { label: 'XLS', className: 'bg-[#217346] text-white' },
    pdf: { label: 'PDF', className: 'bg-[#E4252B] text-white' },
};

const previewBoxClass = 'flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded border';

function extensionFromUrl(url: string): string {
    try {
        const name = new URL(url, 'http://localhost').pathname.split('/').filter(Boolean).pop() ?? '';
        const dot = name.lastIndexOf('.');

        return dot >= 0 ? name.slice(dot + 1).toLowerCase() : '';
    } catch {
        return '';
    }
}

function isImage(url: string) {
    return IMAGE_EXTENSIONS.has(extensionFromUrl(url));
}

function FileTypeIcon({ url, type }: { url: string; type: 'file' | 'link' }) {
    const ext = extensionFromUrl(url);
    const kind = FILE_KIND[ext];

    if (kind) {
        return (
            <span className={`${previewBoxClass} text-[11px] font-bold tracking-wide ${kind.className}`} aria-hidden>
                {kind.label}
            </span>
        );
    }

    if (type === 'link') {
        return (
            <span className={`${previewBoxClass} bg-muted text-muted-foreground`} aria-hidden>
                <Link2 className="h-6 w-6" />
            </span>
        );
    }

    return (
        <span className={`${previewBoxClass} bg-muted text-muted-foreground`} aria-hidden>
            <File className="h-6 w-6" />
        </span>
    );
}

/**
 * Доказательная база — документы одного мероприятия,
 * [[Функциональные требования#4.6 Модуль «Доказательная база»]].
 */
export default function EvidenceShow({ measure, groups, canDelete }: EvidenceShowProps) {
    const [preview, setPreview] = useState<EvidenceFile | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Доказательная база', href: '/evidence' },
        { title: `№${measure.number}. ${measure.title}`, href: route('evidence.show', measure.id) },
    ];

    function destroy(id: number) {
        if (confirm('Удалить документ?')) {
            router.delete(route('evidence.destroy', id));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`№${measure.number}. ${measure.title}`} />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-medium">
                        №{measure.number}. {measure.title}
                    </h1>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('evidence.index')}>Назад</Link>
                    </Button>
                </div>

                {groups.length === 0 && <p className="text-muted-foreground text-sm">Документов не найдено.</p>}

                {groups.map((group) => (
                    <section key={group.date} className="flex flex-col gap-2">
                        <h2 className="text-sm font-medium">{group.label}</h2>
                        <ul className="divide-y rounded-lg border bg-card">
                            {group.files.map((file) => {
                                const label = evidenceLabel(file.url, file.title);
                                const image = isImage(file.url);

                                return (
                                    <li key={file.id} className="flex items-center justify-between gap-3 p-3 text-sm">
                                        <div className="flex min-w-0 items-center gap-3">
                                            {image ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setPreview(file)}
                                                    className={`${previewBoxClass} bg-muted`}
                                                    aria-label={`Превью: ${label}`}
                                                >
                                                    <img src={file.url} alt="" className="h-full w-full object-cover" />
                                                </button>
                                            ) : (
                                                <a
                                                    href={file.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="shrink-0"
                                                    aria-label={label}
                                                >
                                                    <FileTypeIcon url={file.url} type={file.type} />
                                                </a>
                                            )}
                                            <div className="min-w-0">
                                                {image ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setPreview(file)}
                                                        className="break-all text-left font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {label}
                                                    </button>
                                                ) : (
                                                    <a
                                                        href={file.url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="break-all font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {label}
                                                    </a>
                                                )}
                                                <p className="text-muted-foreground">
                                                    {file.type === 'link' ? 'Ссылка' : 'Файл'}
                                                    {file.stage_title && ` · ${file.stage_title}`}
                                                    {file.period && ` · ${file.period}`}
                                                </p>
                                            </div>
                                        </div>
                                        {canDelete && (
                                            <Button size="sm" variant="destructive" onClick={() => destroy(file.id)}>
                                                Удалить
                                            </Button>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                ))}
            </div>

            <Dialog open={preview !== null} onOpenChange={(open) => !open && setPreview(null)}>
                <DialogContent className="max-h-[90vh] max-w-4xl overflow-hidden">
                    <DialogTitle className="pr-8">{preview ? evidenceLabel(preview.url, preview.title) : 'Превью'}</DialogTitle>
                    {preview && (
                        <img
                            src={preview.url}
                            alt={evidenceLabel(preview.url, preview.title)}
                            className="max-h-[75vh] w-full object-contain"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
