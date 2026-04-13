<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

type BlogUser = {
    id: number;
    name: string;
};

type BlogComment = {
    id: number;
    content: string;
    created_at: string;
    user: BlogUser;
};

type BlogPost = {
    id: number;
    user_id: number | null;
    title: string;
    description: string;
    created_at: string;
    updated_at: string;
    comments_count: number;
    comments: BlogComment[];
};

type PostPayload = {
    posts: BlogPost[];
};

type JsonError = {
    error: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Blog',
        href: '/blog',
    },
];

const page = usePage<{ auth: { user: { id: number; is_admin: boolean } } }>();
const posts = ref<BlogPost[]>([]);
const loading = ref(false);
const error = ref('');
const saving = ref(false);
const deleting = ref<number | null>(null);
const commentSaving = ref<number | null>(null);
const commentDeleting = ref<number | null>(null);
const isAdmin = computed(() => page.props.auth?.user?.is_admin ?? false);
const currentUserId = computed(() => page.props.auth?.user?.id);
const canManagePost = (post: BlogPost) => isAdmin.value || post.user_id === currentUserId.value;

const postForm = reactive({
    id: null as number | null,
    title: '',
    description: '',
});

const commentInputs = ref<Record<number, string>>({});

const csrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

const requestJson = async <T,>(input: string, init: RequestInit = {}): Promise<T> => {
    const token = csrfToken();
    const response = await fetch(input, {
        headers: {
            Accept: 'application/json',
            ...(!init.body ? {} : { 'Content-Type': 'application/json' }),
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        credentials: 'same-origin',
        ...init,
    });
    const body = await response.json();

    if (!response.ok) {
        throw new Error((body as JsonError & { message?: string }).error ?? body.message ?? 'Request failed.');
    }

    return body as T;
};

const loadPosts = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await requestJson<PostPayload>('/blog/posts');
        posts.value = response.posts;
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        loading.value = false;
    }
};

const resetPostForm = () => {
    postForm.id = null;
    postForm.title = '';
    postForm.description = '';
};

const submitPost = async (event: Event) => {
    event.preventDefault();
    saving.value = true;
    error.value = '';

    try {
        if (!postForm.title.trim() || !postForm.description.trim()) {
            throw new Error('Title and description are required.');
        }

        const payload = {
            title: postForm.title.trim(),
            description: postForm.description.trim(),
        };

        if (postForm.id === null) {
            await requestJson('/blog/posts', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        } else {
            await requestJson(`/blog/posts/${postForm.id}`, {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
        }

        resetPostForm();
        await loadPosts();
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        saving.value = false;
    }
};

const editPost = (post: BlogPost) => {
    postForm.id = post.id;
    postForm.title = post.title;
    postForm.description = post.description;
};

const deletePost = async (postId: number) => {
    if (!confirm('Delete this post?')) {
        return;
    }

    deleting.value = postId;
    error.value = '';

    try {
        await requestJson(`/blog/posts/${postId}`, {
            method: 'DELETE',
        });
        await loadPosts();
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        deleting.value = null;
    }
};

const addComment = async (postId: number) => {
    const content = (commentInputs.value[postId] ?? '').trim();
    if (!content) {
        error.value = 'Comment cannot be empty.';
        return;
    }

    commentSaving.value = postId;
    error.value = '';

    try {
        await requestJson(`/blog/posts/${postId}/comments`, {
            method: 'POST',
            body: JSON.stringify({ content }),
        });
        commentInputs.value[postId] = '';
        await loadPosts();
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        commentSaving.value = null;
    }
};

const deleteComment = async (comment: BlogComment) => {
    if (!confirm('Delete this comment?')) {
        return;
    }

    commentDeleting.value = comment.id;
    error.value = '';

    try {
        await requestJson(`/blog/comments/${comment.id}`, {
            method: 'DELETE',
        });
        await loadPosts();
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        commentDeleting.value = null;
    }
};

const formatDate = (value: string) => {
    return new Date(value).toLocaleString();
};

onMounted(async () => {
    await loadPosts();
});
</script>

<template>
    <Head title="Blog" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-3 rounded-xl p-3">
            <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-3">
                <h2 class="text-base font-semibold">Blog</h2>

                <p v-if="error" class="mt-2 rounded-md bg-rose-900/20 p-1.5 text-xs text-rose-200">
                    {{ error }}
                </p>

                <form class="mt-2 grid gap-1.5" @submit="submitPost">
                    <label class="text-sm font-medium" for="post-title">Post title</label>
                    <input
                        id="post-title"
                        v-model="postForm.title"
                        class="rounded-md border border-slate-300/40 bg-black/5 px-2.5 py-1.5 text-sm"
                        placeholder="Post title"
                        type="text"
                    />

                    <label class="mt-1 text-sm font-medium" for="post-description">Description</label>
                    <textarea
                        id="post-description"
                        v-model="postForm.description"
                        class="min-h-20 rounded-md border border-slate-300/40 bg-black/5 px-2.5 py-1.5 text-sm"
                        placeholder="Post description"
                    ></textarea>

                    <div class="mt-1 flex gap-1.5">
                        <button
                            :disabled="saving"
                            class="rounded-md bg-sky-500 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
                            type="submit"
                        >
                            {{ postForm.id === null ? 'Create post' : 'Save changes' }}
                        </button>
                        <button
                            class="rounded-md border border-slate-300/40 bg-black/5 px-3 py-1.5 text-sm"
                            type="button"
                            @click="resetPostForm"
                        >
                            Clear
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-3">
                <h2 class="text-base font-semibold">Posts</h2>
                <p v-if="loading" class="mt-1.5 text-sm text-slate-500">Loading...</p>

                <div v-if="!loading && posts.length === 0" class="mt-1.5 text-sm text-slate-500">No posts yet.</div>

                <div class="mt-2 space-y-2">
                    <article
                        v-for="post in posts"
                        :key="post.id"
                        class="rounded-md border border-slate-300/40 bg-black/5 p-3"
                    >
                        <div class="space-y-0.5">
                            <p class="text-base font-semibold leading-tight">{{ post.title }}</p>
                            <p class="text-xs text-slate-500">
                                {{ formatDate(post.created_at) }} · {{ post.comments_count }} comments
                            </p>
                        </div>
                        <p class="mt-1.5 text-sm text-slate-700 dark:text-slate-100">{{ post.description }}</p>

                        <div v-if="canManagePost(post)" class="mt-2 flex justify-end gap-1.5">
                            <button
                                type="button"
                                class="rounded-md border border-emerald-500 text-emerald-700 px-2.5 py-1 text-xs"
                                @click="editPost(post)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-rose-300/50 px-2.5 py-1 text-xs text-rose-700"
                                :disabled="deleting === post.id"
                                @click="deletePost(post.id)"
                            >
                                {{ deleting === post.id ? 'Deleting…' : 'Delete' }}
                            </button>
                        </div>

                        <div class="mt-2.5 border-t border-slate-300/40 pt-2">
                            <p class="text-sm font-medium">Comments</p>
                            <div v-if="post.comments.length === 0" class="mt-1.5 text-xs text-slate-500">No comments yet.</div>

                            <div class="mt-1.5 space-y-1.5">
                                <div v-for="comment in post.comments" :key="comment.id" class="rounded-md border border-slate-300/40 bg-black/5 p-2 text-xs">
                                    <div class="flex justify-between gap-2">
                                        <p class="font-medium">{{ comment.user?.name || 'Unknown' }}</p>
                                        <p class="text-slate-500">{{ formatDate(comment.created_at) }}</p>
                                    </div>
                                    <p class="mt-1 text-slate-700 dark:text-slate-100">{{ comment.content }}</p>
                                    <div
                                        v-if="isAdmin || comment.user.id === currentUserId"
                                        class="mt-1 text-right"
                                    >
                                        <button
                                            class="rounded-md border border-rose-300/50 px-2 py-0.5 text-[11px] text-rose-700"
                                            :disabled="commentDeleting === comment.id"
                                            type="button"
                                            @click="deleteComment(comment)"
                                        >
                                            {{ commentDeleting === comment.id ? 'Deleting…' : 'Delete' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-1.5">
                                <textarea
                                    v-model="commentInputs[post.id]"
                                    class="min-h-14 w-full rounded-md border border-slate-300/40 bg-black/5 px-2.5 py-1.5 text-xs"
                                    placeholder="Add a comment..."
                                ></textarea>
                                <button
                                    class="mt-1.5 rounded-md bg-sky-500 px-2.5 py-1 text-xs font-semibold text-white disabled:opacity-50"
                                    :disabled="commentSaving === post.id"
                                    type="button"
                                    @click="addComment(post.id)"
                                >
                                    {{ commentSaving === post.id ? 'Sending…' : 'Add comment' }}
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
