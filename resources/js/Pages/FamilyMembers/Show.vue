<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    member: Object,
    relationshipTypes: Object,
});

const formatDate = (date) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getRelationshipLabel = (slug) => {
    const allTypes = [
        ...(props.relationshipTypes?.immediate || []),
        ...(props.relationshipTypes?.extended || []),
        ...(props.relationshipTypes?.non_family || []),
    ];
    const type = allTypes.find(t => t.slug === slug);
    return type?.label || slug;
};

// Group relationships by type for display
const groupedRelationships = () => {
    if (!props.member.all_related_members) return {};
    const groups = {};
    props.member.all_related_members.forEach(m => {
        const type = m.pivot.relationship_type;
        const label = getRelationshipLabel(type);
        if (!groups[type]) {
            groups[type] = { label, members: [] };
        }
        groups[type].members.push(m);
    });
    return groups;
};

const hasRelationships = () => {
    return props.member.all_related_members?.length > 0;
};
</script>

<template>
    <AppLayout :title="member.display_name">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('family-members.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ member.display_name }}
                    </h2>
                </div>
                <Link
                    :href="route('family-members.edit', member.id)"
                    class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-md transition"
                >
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Profile Card -->
                    <div class="lg:col-span-1">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <!-- Photo -->
                            <div class="w-32 h-32 mx-auto mb-4 rounded-full bg-gradient-to-br from-amber-100 to-amber-200 dark:from-amber-900 dark:to-amber-800 flex items-center justify-center overflow-hidden">
                                <img
                                    v-if="member.photo_path"
                                    :src="`/storage/${member.photo_path}`"
                                    :alt="member.display_name"
                                    class="w-full h-full object-cover"
                                />
                                <span v-else class="text-4xl font-bold text-amber-600 dark:text-amber-400">
                                    {{ member.first_name?.[0] }}{{ member.last_name?.[0] || '' }}
                                </span>
                            </div>

                            <!-- Name -->
                            <h3 class="text-xl font-bold text-center text-gray-900 dark:text-white">
                                {{ member.full_name }}
                            </h3>
                            <p v-if="member.nickname" class="text-center text-gray-500 dark:text-gray-400">
                                "{{ member.nickname }}"
                            </p>

                            <!-- Status Badge -->
                            <div class="flex justify-center mt-3">
                                <span
                                    :class="member.is_living ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                                    class="px-3 py-1 text-sm font-medium rounded-full"
                                >
                                    {{ member.is_living ? 'Living' : 'Deceased' }}
                                </span>
                            </div>

                            <!-- Details -->
                            <div class="mt-6 space-y-3 text-sm">
                                <div v-if="member.birth_date" class="flex items-center text-gray-600 dark:text-gray-400">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Born: {{ formatDate(member.birth_date) }}
                                </div>
                                <div v-if="member.death_date" class="flex items-center text-gray-600 dark:text-gray-400">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Died: {{ formatDate(member.death_date) }}
                                </div>
                                <div v-if="member.age" class="flex items-center text-gray-600 dark:text-gray-400">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ member.is_living ? `${member.age} years old` : `Lived ${member.age} years` }}
                                </div>
                                <div v-if="member.gender" class="flex items-center text-gray-600 dark:text-gray-400 capitalize">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    {{ member.gender }}
                                </div>
                            </div>

                            <!-- Notes -->
                            <div v-if="member.notes" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Notes</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ member.notes }}</p>
                            </div>

                            <!-- Stats -->
                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                        {{ member.timeline_entries_count }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">Timeline Events</div>
                                </div>
                            </div>
                        </div>

                        <!-- Relationships -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mt-6">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Family Relationships</h4>

                            <template v-if="hasRelationships()">
                                <div
                                    v-for="(group, type) in groupedRelationships()"
                                    :key="type"
                                    class="mb-4"
                                >
                                    <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">
                                        {{ group.label }}
                                    </h5>
                                    <div class="space-y-2">
                                        <Link
                                            v-for="rel in group.members"
                                            :key="rel.id"
                                            :href="route('family-members.show', rel.id)"
                                            class="flex items-center p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                                        >
                                            <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center text-sm font-medium text-amber-600 dark:text-amber-400 mr-2 overflow-hidden">
                                                <img
                                                    v-if="rel.photo_path"
                                                    :src="`/storage/${rel.photo_path}`"
                                                    class="w-full h-full object-cover"
                                                />
                                                <span v-else>{{ rel.first_name?.[0] }}</span>
                                            </div>
                                            <span class="text-gray-900 dark:text-white">{{ rel.display_name }}</span>
                                        </Link>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                                No relationships added yet.
                                <Link :href="route('family-members.edit', member.id)" class="text-amber-600 hover:underline">
                                    Add relationships
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        Timeline Events
                                    </h3>
                                    <Link
                                        :href="route('timeline.index', { member: member.id })"
                                        class="text-sm text-amber-600 hover:text-amber-700 dark:text-amber-400"
                                    >
                                        View All
                                    </Link>
                                </div>
                            </div>

                            <div v-if="member.timeline_entries?.length" class="divide-y divide-gray-200 dark:divide-gray-700">
                                <Link
                                    v-for="entry in member.timeline_entries"
                                    :key="entry.id"
                                    :href="route('timeline.show', entry.id)"
                                    class="block p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                                >
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center">
                                            <span class="text-amber-600 dark:text-amber-400 text-sm font-medium">
                                                {{ entry.event_type?.[0]?.toUpperCase() }}
                                            </span>
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ entry.title }}
                                            </h4>
                                            <p v-if="entry.event_date" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ formatDate(entry.event_date) }}
                                            </p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 capitalize">
                                            {{ entry.event_type }}
                                        </span>
                                    </div>
                                </Link>
                            </div>

                            <div v-else class="p-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400">No timeline events yet.</p>
                                <Link
                                    :href="route('timeline.create')"
                                    class="mt-4 inline-flex items-center text-sm text-amber-600 hover:text-amber-700"
                                >
                                    Create an event
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
