import { Head, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { formatDateTime, PageHeader, Pager, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Post { id: string; title: string; slug: string; excerpt: string | null; category: string | null; author: string | null; status: string; published: string | null }

export default function Posts({ posts, categories }: {
    posts: { data: Post[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
    categories: { key: string; label: string }[];
}) {
    const [writing, setWriting] = useState(false);
    const post = useForm({ title: '', excerpt: '', body: '', category: '', author_name: '', publish: false });
    const category = useForm({ name: '' });

    return (
        <>
            <Head title="Articles" />
            <div className="mx-auto grid max-w-4xl gap-5">
                <PageHeader title="Articles" description="News and insight pieces for the website. Good articles are the cheapest way to be found in search."
                    action={<Button onClick={() => setWriting(!writing)}>Write an article</Button>} />

                {writing && (
                    <form onSubmit={(e) => { e.preventDefault(); post.transform((d) => ({ ...d, category: d.category || null })); post.post('/website/articles', { preserveScroll: true, onSuccess: () => { post.reset(); setWriting(false); } }); }}
                        className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Title" name="title" value={post.data.title} onChange={(e) => post.setData('title', e.target.value)} error={post.errors.title} />
                            <SelectField label="Category" name="category" value={post.data.category} onChange={(v) => post.setData('category', v)} options={categories} placeholder="None" />
                            <Field label="Author" name="author_name" value={post.data.author_name} onChange={(e) => post.setData('author_name', e.target.value)} placeholder="Your name" />
                        </div>
                        <Field label="Summary shown in listings" name="excerpt" value={post.data.excerpt} onChange={(e) => post.setData('excerpt', e.target.value)} />
                        <label className="grid gap-1.5 text-sm font-medium">Article
                            <textarea rows={10} value={post.data.body} onChange={(e) => post.setData('body', e.target.value)} className="rounded-[var(--radius-control)] border border-concrete p-3 font-normal" />
                        </label>
                        {post.errors.body && <p className="text-sm text-brick">{post.errors.body}</p>}
                        <div className="flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={post.data.publish} onChange={(e) => post.setData('publish', e.target.checked)} /> Publish it now</label>
                            <Button type="submit" disabled={post.processing}>Save article</Button>
                        </div>
                    </form>
                )}

                <ul className="grid gap-2">
                    {posts.data.map((p) => (
                        <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-[var(--radius-panel)] border border-concrete bg-surface p-3">
                            <span>
                                <span className="font-semibold">{p.title}</span>
                                <span className="block text-sm text-ink-soft">{[p.category, p.author, p.excerpt].filter(Boolean).join(' · ')}</span>
                            </span>
                            <span className={cn('text-sm', p.status === 'published' ? 'text-line-deep' : 'text-ink-soft')}>
                                {p.status === 'published' ? `Published ${formatDateTime(p.published)}` : 'Draft'}
                            </span>
                        </li>
                    ))}
                    {posts.data.length === 0 && <li className="text-ink-soft">No articles yet.</li>}
                </ul>
                <Pager prev={posts.prev_page_url} next={posts.next_page_url} page={posts.current_page} last={posts.last_page} />

                <form onSubmit={(e) => { e.preventDefault(); category.post('/website/categories', { preserveScroll: true, onSuccess: () => category.reset() }); }} className="flex items-end gap-3">
                    <div className="w-64"><Field label="New category" name="name" value={category.data.name} onChange={(e) => category.setData('name', e.target.value)} /></div>
                    <Button variant="ghost" type="submit">Add category</Button>
                </form>
            </div>
        </>
    );
}

Posts.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
