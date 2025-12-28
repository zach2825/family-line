<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    members: Array,
    relationshipTypes: Object,
});

const page = usePage();

// Get the current user's linked family member
const currentFamilyMember = computed(() => page.props.currentFamilyMember);

const search = ref('');
const viewMode = ref('grid'); // 'grid' or 'list'

const filteredMembers = computed(() => {
    if (!search.value) return props.members;

    const query = search.value.toLowerCase();
    return props.members.filter(member =>
        member.first_name?.toLowerCase().includes(query) ||
        member.last_name?.toLowerCase().includes(query) ||
        member.nickname?.toLowerCase().includes(query)
    );
});

const livingCount = computed(() => props.members.filter(m => m.is_living).length);
const deceasedCount = computed(() => props.members.filter(m => !m.is_living).length);

// Check if a member is linked to the current user
const isMe = (member) => {
    return currentFamilyMember.value && currentFamilyMember.value.id === member.id;
};
</script>

<template>
    <AppLayout title="Family Members">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Family Members
                </h2>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('family-relationships.index')"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-amber-600 dark:hover:text-amber-400"
                    >
                        <svg class="w-5 h-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        Relationships
                    </Link>
                    <Link
                        :href="route('family.tree')"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-amber-600 dark:hover:text-amber-400"
                    >
                        <svg class="w-5 h-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Family Tree
                    </Link>
                    <Link
                        :href="route('family-members.create')"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-md transition"
                    >
                        <svg class="w-5 h-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Member
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ members.length }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Members</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400">{{ livingCount }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Living</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-3xl font-bold text-gray-600 dark:text-gray-400">{{ deceasedCount }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Deceased</div>
                    </div>
                </div>

                <!-- Search & View Toggle -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                    <div class="flex items-center justify-between">
                        <div class="relative flex-1 max-w-md">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search by name or nickname..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white rounded-lg focus:ring-amber-500 focus:border-amber-500"
                            />
                            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <div class="flex items-center space-x-2 ml-4">
                            <button
                                @click="viewMode = 'grid'"
                                :class="['p-2 rounded', viewMode === 'grid' ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400' : 'text-gray-400 hover:text-gray-600']"
                            >
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                            </button>
                            <button
                                @click="viewMode = 'list'"
                                :class="['p-2 rounded', viewMode === 'list' ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400' : 'text-gray-400 hover:text-gray-600']"
                            >
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="members.length === 0" class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No family members yet</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6">Start building your family tree by adding your first member.</p>
                    <Link
                        :href="route('family-members.create')"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-md transition"
                    >
                        Add Your First Family Member
                    </Link>
                </div>

                <!-- Grid View -->
                <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Link
                        v-for="member in filteredMembers"
                        :key="member.id"
                        :href="route('family-members.show', member.id)"
                        :class="[
                            'bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition p-4 text-center group relative',
                            isMe(member) ? 'ring-2 ring-blue-400 dark:ring-blue-500' : ''
                        ]"
                    >
                        <!-- "You" Badge -->
                        <span
                            v-if="isMe(member)"
                            class="absolute -top-2 -right-2 px-2 py-0.5 bg-blue-500 text-white text-xs font-bold rounded-full shadow"
                        >
                            You
                        </span>
                        <div class="w-20 h-20 mx-auto mb-3 rounded-full bg-gradient-to-br from-amber-100 to-amber-200 dark:from-amber-900 dark:to-amber-800 flex items-center justify-center overflow-hidden">
                            <img
                                v-if="member.photo_path"
                                :src="`/storage/${member.photo_path}`"
                                :alt="member.display_name"
                                class="w-full h-full object-cover"
                            />
                            <span v-else class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                {{ member.first_name?.[0] }}{{ member.last_name?.[0] || '' }}
                            </span>
                        </div>
                        <h3 class="font-medium text-gray-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition">
                            {{ member.display_name }}
                        </h3>
                        <p v-if="member.nickname && member.nickname !== member.display_name" class="text-sm text-gray-500 dark:text-gray-400">
                            {{ member.full_name }}
                        </p>
                        <div class="mt-2 flex items-center justify-center space-x-2">
                            <span v-if="member.age" class="text-xs text-gray-500 dark:text-gray-400">
                                {{ member.is_living ? `${member.age} years old` : `Lived ${member.age} years` }}
                            </span>
                            <span v-if="!member.is_living" class="text-xs text-gray-400">
                                <svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12z"/>
                                </svg>
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-gray-400">
                            {{ member.timeline_entries_count }} events
                        </div>
                    </Link>
                </div>

                <!-- List View -->
                <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nickname</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Events</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="member in filteredMembers"
                                :key="member.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                @click="$inertia.visit(route('family-members.show', member.id))"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center overflow-hidden mr-3">
                                            <img
                                                v-if="member.photo_path"
                                                :src="`/storage/${member.photo_path}`"
                                                :alt="member.display_name"
                                                class="w-full h-full object-cover"
                                            />
                                            <span v-else class="text-sm font-medium text-amber-600 dark:text-amber-400">
                                                {{ member.first_name?.[0] }}{{ member.last_name?.[0] || '' }}
                                            </span>
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ member.full_name }}</span>
                                        <span
                                            v-if="isMe(member)"
                                            class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200 text-xs font-medium rounded-full"
                                        >
                                            You
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {{ member.nickname || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {{ member.age ? `${member.age}` : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {{ member.timeline_entries_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="member.is_living ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                    >
                                        {{ member.is_living ? 'Living' : 'Deceased' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
