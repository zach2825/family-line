<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    entry: Object,
    eventTypes: Object,
});

const confirmingDeletion = ref(false);

const deleteEntry = () => {
    router.delete(route('timeline.destroy', props.entry.id));
};

const getEventTypeColor = (type) => {
    const colors = {
        story: 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
        birth: 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
        death: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        marriage: 'bg-pink-100 text-pink-800 dark:bg-pink-800 dark:text-pink-100',
        milestone: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
        photo: 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100',
        document: 'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100',
        memory: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-800 dark:text-indigo-100',
        tradition: 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
    };
    return colors[type] || colors.story;
};
</script>

<template>
    <AppLayout title="View Timeline Entry">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <Link :href="route('timeline.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ entry.title }}
                    </h2>
                </div>
                <div class="flex space-x-2">
                    <Link
                        :href="route('timeline.edit', entry.id)"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Edit
                    </Link>
                    <DangerButton v-if="!confirmingDeletion" @click="confirmingDeletion = true">
                        Delete
                    </DangerButton>
                    <template v-else>
                        <SecondaryButton @click="confirmingDeletion = false">
                            Cancel
                        </SecondaryButton>
                        <DangerButton @click="deleteEntry">
                            Confirm
                        </DangerButton>
                    </template>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- Header Info -->
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-6 pb-6 border-b dark:border-gray-700">
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    :class="getEventTypeColor(entry.event_type)"
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                >
                                    {{ eventTypes[entry.event_type] }}
                                </span>

                                <span v-if="entry.visibility !== 'immediate_family'" class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ entry.visibility === 'private' ? 'Private' : 'Extended Family' }}
                                </span>
                            </div>

                            <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                                <div v-if="entry.event_date" class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ new Date(entry.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                                    <span v-if="entry.event_end_date">
                                        - {{ new Date(entry.event_end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                                    </span>
                                </div>
                                <div v-if="entry.location" class="mt-1 flex items-center justify-end">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    {{ entry.location }}
                                </div>
                            </div>
                        </div>

                        <!-- Family Members Involved -->
                        <div v-if="entry.family_members?.length" class="mb-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Family Members</h3>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="member in entry.family_members"
                                    :key="member.id"
                                    :href="route('family-members.show', member.id)"
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-800 transition"
                                >
                                    <span
                                        v-if="member.photo_path"
                                        class="w-5 h-5 rounded-full overflow-hidden mr-2 -ml-1"
                                    >
                                        <img :src="`/storage/${member.photo_path}`" class="w-full h-full object-cover" />
                                    </span>
                                    {{ member.nickname || `${member.first_name} ${member.last_name || ''}`.trim() }}
                                </Link>
                            </div>
                        </div>

                        <!-- People Involved (text-based, fallback) -->
                        <div v-if="entry.people_involved?.length" class="mb-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Other People</h3>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="person in entry.people_involved"
                                    :key="person"
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200"
                                >
                                    {{ person }}
                                </span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="prose dark:prose-invert max-w-none">
                            <div v-if="entry.content" class="whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ entry.content }}</div>
                            <p v-else class="text-gray-400 dark:text-gray-500 italic">No content yet.</p>
                        </div>

                        <!-- Audio Transcript -->
                        <div v-if="entry.has_audio && entry.audio_transcript" class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                </svg>
                                Audio Transcript
                            </h3>
                            <p class="text-gray-600 dark:text-gray-300 text-sm">{{ entry.audio_transcript }}</p>
                        </div>

                        <!-- Meta Info -->
                        <div class="mt-8 pt-6 border-t dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">
                            <div class="flex justify-between">
                                <span>Added by {{ entry.user.name }}</span>
                                <span>
                                    {{ new Date(entry.created_at).toLocaleDateString() }}
                                    <span v-if="entry.updated_at !== entry.created_at">
                                        (Updated {{ new Date(entry.updated_at).toLocaleDateString() }})
                                    </span>
                                </span>
                            </div>
                            <div v-if="entry.family_surname" class="mt-2">
                                Family: {{ entry.family_surname }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
