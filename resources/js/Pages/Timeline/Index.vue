<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    entries: Object,
    eventTypes: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const showFilters = ref(false);
const localFilters = ref({
    event_type: props.filters.event_type || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    search: props.filters.search || '',
    person: props.filters.person || '',
});

const hasActiveFilters = computed(() => {
    return Object.values(localFilters.value).some(v => v);
});

const applyFilters = () => {
    router.get(route('timeline.index'), localFilters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    localFilters.value = {
        event_type: '',
        date_from: '',
        date_to: '',
        search: '',
        person: '',
    };
    applyFilters();
};

// Event type styling
const getEventTypeStyle = (type) => {
    const styles = {
        story: { bg: 'bg-amber-500', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
        birth: { bg: 'bg-green-500', icon: 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7' },
        death: { bg: 'bg-gray-500', icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
        marriage: { bg: 'bg-pink-500', icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
        milestone: { bg: 'bg-blue-500', icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
        photo: { bg: 'bg-purple-500', icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
        document: { bg: 'bg-indigo-500', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        memory: { bg: 'bg-rose-500', icon: 'M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        tradition: { bg: 'bg-orange-500', icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z' },
    };
    return styles[type] || styles.story;
};

const getEventTypeColor = (type) => {
    const colors = {
        story: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
        birth: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        death: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        marriage: 'bg-pink-100 text-pink-800 dark:bg-pink-900/50 dark:text-pink-300',
        milestone: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        photo: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300',
        document: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300',
        memory: 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300',
        tradition: 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300',
    };
    return colors[type] || colors.story;
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getYear = (date) => {
    if (!date) return '';
    return new Date(date).getFullYear();
};
</script>

<template>
    <AppLayout title="Family Timeline">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Family Timeline
                </h2>
                <div class="flex items-center space-x-3">
                    <button
                        @click="showFilters = !showFilters"
                        :class="[
                            'inline-flex items-center px-3 py-2 border rounded-md text-sm font-medium transition',
                            hasActiveFilters
                                ? 'border-amber-500 text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20'
                                : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filters
                        <span v-if="hasActiveFilters" class="ml-2 w-2 h-2 bg-amber-500 rounded-full"></span>
                    </button>
                    <Link
                        :href="route('timeline.create')"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Entry
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 relative">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Empty State -->
                <div v-if="entries.data.length === 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-8 text-center">
                    <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">
                        {{ hasActiveFilters ? 'No entries match your filters.' : 'No timeline entries yet. Start documenting your family history!' }}
                    </p>
                    <Link
                        v-if="!hasActiveFilters"
                        :href="route('timeline.create')"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-md font-medium transition"
                    >
                        Create your first entry
                    </Link>
                    <button
                        v-else
                        @click="clearFilters"
                        class="text-amber-600 hover:text-amber-700 dark:text-amber-400 font-medium"
                    >
                        Clear filters
                    </button>
                </div>

                <!-- Staggered Timeline -->
                <div v-else class="relative">
                    <!-- Center Timeline Line -->
                    <div class="absolute left-1/2 transform -translate-x-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-amber-400 via-amber-500 to-amber-600 dark:from-amber-600 dark:via-amber-700 dark:to-amber-800 rounded-full"></div>

                    <!-- Timeline Entries -->
                    <div class="space-y-4">
                        <div
                            v-for="(entry, index) in entries.data"
                            :key="entry.id"
                            class="relative flex items-center"
                        >
                            <!-- Left Side: Card or Spacer -->
                            <div class="w-5/12 pr-8">
                                <Link v-if="index % 2 === 0" :href="route('timeline.show', entry.id)" class="block group">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 dark:border-gray-700 hover:border-amber-300 dark:hover:border-amber-600 transform hover:-translate-y-1 text-right">
                                        <!-- Date Badge -->
                                        <div class="flex justify-end mb-3">
                                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full">
                                                {{ formatDate(entry.event_date) }}
                                            </span>
                                        </div>

                                        <!-- Title & Type -->
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors mb-2">
                                            {{ entry.title }}
                                        </h3>

                                        <span :class="getEventTypeColor(entry.event_type)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                            {{ eventTypes[entry.event_type] }}
                                        </span>

                                        <!-- Content Preview -->
                                        <p v-if="entry.content" class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                            {{ entry.content.substring(0, 120) }}{{ entry.content.length > 120 ? '...' : '' }}
                                        </p>

                                        <!-- Meta Info -->
                                        <div class="mt-4 flex items-center justify-end gap-4 text-xs text-gray-500 dark:text-gray-400">
                                            <span v-if="entry.location" class="flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                </svg>
                                                {{ entry.location }}
                                            </span>
                                            <span v-if="entry.people_involved?.length" class="flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                                </svg>
                                                {{ entry.people_involved.length }}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            </div>

                            <!-- Center Icon Column -->
                            <div class="w-2/12 flex justify-center relative z-10">
                                <div :class="[getEventTypeStyle(entry.event_type).bg, 'w-12 h-12 rounded-full flex items-center justify-center shadow-lg ring-4 ring-white dark:ring-gray-900']">
                                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="getEventTypeStyle(entry.event_type).icon" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Right Side: Card or Spacer -->
                            <div class="w-5/12 pl-8">
                                <Link v-if="index % 2 !== 0" :href="route('timeline.show', entry.id)" class="block group">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 dark:border-gray-700 hover:border-amber-300 dark:hover:border-amber-600 transform hover:-translate-y-1">
                                        <!-- Date Badge -->
                                        <div class="flex justify-start mb-3">
                                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full">
                                                {{ formatDate(entry.event_date) }}
                                            </span>
                                        </div>

                                        <!-- Title & Type -->
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors mb-2">
                                            {{ entry.title }}
                                        </h3>

                                        <span :class="getEventTypeColor(entry.event_type)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                            {{ eventTypes[entry.event_type] }}
                                        </span>

                                        <!-- Content Preview -->
                                        <p v-if="entry.content" class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                            {{ entry.content.substring(0, 120) }}{{ entry.content.length > 120 ? '...' : '' }}
                                        </p>

                                        <!-- Meta Info -->
                                        <div class="mt-4 flex items-center justify-start gap-4 text-xs text-gray-500 dark:text-gray-400">
                                            <span v-if="entry.location" class="flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                </svg>
                                                {{ entry.location }}
                                            </span>
                                            <span v-if="entry.people_involved?.length" class="flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                                </svg>
                                                {{ entry.people_involved.length }}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline End Cap -->
                    <div class="absolute left-1/2 transform -translate-x-1/2 -bottom-4">
                        <div class="w-4 h-4 rounded-full bg-amber-600 ring-4 ring-white dark:ring-gray-900"></div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="entries.links && entries.links.length > 3" class="mt-12 flex justify-center">
                    <nav class="flex space-x-2">
                        <template v-for="link in entries.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-4 py-2 text-sm rounded-lg transition font-medium"
                                :class="{
                                    'bg-amber-600 text-white shadow-md': link.active,
                                    'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700': !link.active
                                }"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-4 py-2 text-sm text-gray-400 dark:text-gray-600"
                                v-html="link.label"
                            />
                        </template>
                    </nav>
                </div>
            </div>

            <!-- Slide-out Filter Panel -->
            <Transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <div
                    v-show="showFilters"
                    class="fixed right-0 top-0 h-full w-80 bg-white dark:bg-gray-800 shadow-2xl z-50 overflow-y-auto"
                >
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filters</h3>
                            <button
                                @click="showFilters = false"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            >
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-6">
                            <!-- Search -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Search
                                </label>
                                <input
                                    v-model="localFilters.search"
                                    type="text"
                                    placeholder="Search titles, content..."
                                    class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500"
                                />
                            </div>

                            <!-- Event Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Event Type
                                </label>
                                <select
                                    v-model="localFilters.event_type"
                                    class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500"
                                >
                                    <option value="">All Types</option>
                                    <option v-for="(label, value) in eventTypes" :key="value" :value="value">
                                        {{ label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Person -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Person Involved
                                </label>
                                <input
                                    v-model="localFilters.person"
                                    type="text"
                                    placeholder="e.g., Mom, Grandpa"
                                    class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500"
                                />
                            </div>

                            <!-- Date Range -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Date Range
                                </label>
                                <div class="space-y-2">
                                    <input
                                        v-model="localFilters.date_from"
                                        type="date"
                                        class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500"
                                    />
                                    <div class="text-center text-gray-400 text-sm">to</div>
                                    <input
                                        v-model="localFilters.date_to"
                                        type="date"
                                        class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-8 space-y-3">
                            <button
                                @click="applyFilters"
                                class="w-full py-2 px-4 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-md transition"
                            >
                                Apply Filters
                            </button>
                            <button
                                v-if="hasActiveFilters"
                                @click="clearFilters"
                                class="w-full py-2 px-4 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium rounded-md transition"
                            >
                                Clear All
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Backdrop -->
            <Transition
                enter-active-class="transition-opacity duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-show="showFilters"
                    @click="showFilters = false"
                    class="fixed inset-0 bg-black/30 z-40"
                ></div>
            </Transition>
        </div>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
