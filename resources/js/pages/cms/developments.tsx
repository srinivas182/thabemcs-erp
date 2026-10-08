import { Head, Link, router } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { PageHeader } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Development { id: string; name: string; town: string | null; stage: string; image: string | null; imageId: string | null }
interface Media { id: string; url: string; name: string; alt: string | null }

/** Chooses the photograph the public website shows for each development. */
export default function Developments({ developments, media }: { developments: Development[]; media: Media[] }) {
    const [choosing, setChoosing] = useState<string | null>(null);

    const set = (project: string, mediaId: string | null) => {
        router.put(`/website/developments/${project}`, { media: mediaId }, { preserveScroll: true, onSuccess: () => setChoosing(null) });
    };

    return (
        <>
            <Head title="Development photographs" />
            <div className="mx-auto grid max-w-4xl gap-5">
                <PageHeader
                    title="Development photographs"
                    description="What the website shows for each development. Without a photograph it falls back to an illustration."
                    action={<Button variant="ghost" asChild><Link href="/website/media">Media library</Link></Button>}
                />

                {media.length === 0 && (
                    <p className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-5 text-ink-soft">
                        No images yet. Upload some under <Link href="/website/media" className="text-line underline">Media</Link> first.
                    </p>
                )}

                <ul className="grid gap-3">
                    {developments.map((development) => (
                        <li key={development.id} className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                            <div className="flex flex-wrap items-center gap-4">
                                {development.image ? (
                                    <img src={development.image} alt="" className="h-20 w-32 rounded-[var(--radius-control)] object-cover" />
                                ) : (
                                    <span className="flex h-20 w-32 items-center justify-center rounded-[var(--radius-control)] border border-dashed border-concrete text-xs text-ink-soft">
                                        Illustration
                                    </span>
                                )}
                                <div className="flex-1">
                                    <p className="font-semibold">{development.name}</p>
                                    <p className="text-sm text-ink-soft">{[development.town, development.stage].filter(Boolean).join(' · ')}</p>
                                </div>
                                <span className="flex gap-2">
                                    <Button size="sm" variant="secondary" onClick={() => setChoosing(choosing === development.id ? null : development.id)}>
                                        {development.image ? 'Change' : 'Choose a photograph'}
                                    </Button>
                                    {development.image && (
                                        <Button size="sm" variant="ghost" className="text-brick" onClick={() => set(development.id, null)}>
                                            Remove
                                        </Button>
                                    )}
                                </span>
                            </div>

                            {choosing === development.id && (
                                <div className="mt-4 flex flex-wrap gap-2 border-t border-concrete pt-4">
                                    {media.map((image) => (
                                        <button
                                            key={image.id}
                                            onClick={() => set(development.id, image.id)}
                                            aria-label={image.alt ?? image.name}
                                            className={cn('size-20 overflow-hidden rounded-[var(--radius-control)] border-2',
                                                development.imageId === image.id ? 'border-line' : 'border-concrete hover:border-ink')}
                                        >
                                            <img src={image.url} alt="" className="size-full object-cover" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </li>
                    ))}
                    {developments.length === 0 && <li className="text-ink-soft">No developments yet.</li>}
                </ul>
            </div>
        </>
    );
}

Developments.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
