<script setup>
import { ref, watch, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    // Legacy prop for backward compatibility
    legacyNames: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'update:legacyNames']);

const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showDropdown = ref(false);
const showQuickCreate = ref(false);
const quickCreateName = ref('');

let searchTimeout = null;

// Search for family members
const searchMembers = async () => {
    if (searchQuery.value.length < 1) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;

    try {
        const response = await axios.get(route('api.family-members.search'), {
            params: { q: searchQuery.value },
        });
        searchResults.value = response.data.members;
    } catch (err) {
        console.error('Search error:', err);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

// Debounced search
watch(searchQuery, (val) => {
    clearTimeout(searchTimeout);
    if (val.length >= 1) {
        showDropdown.value = true;
        searchTimeout = setTimeout(searchMembers, 300);
    } else {
        showDropdown.value = false;
        searchResults.value = [];
    }
});

// Add a member to the selection
const addMember = (member) => {
    if (!props.modelValue.find(m => m.id === member.id)) {
        emit('update:modelValue', [...props.modelValue, member]);
    }
    searchQuery.value = '';
    showDropdown.value = false;
};

// Remove a member from the selection
const removeMember = (memberId) => {
    emit('update:modelValue', props.modelValue.filter(m => m.id !== memberId));
};

// Add a legacy text name (for backward compatibility)
const addLegacyName = (name) => {
    const trimmed = name.trim();
    if (trimmed && !props.legacyNames.includes(trimmed)) {
        emit('update:legacyNames', [...props.legacyNames, trimmed]);
    }
    searchQuery.value = '';
    showDropdown.value = false;
};

// Remove a legacy name
const removeLegacyName = (name) => {
    emit('update:legacyNames', props.legacyNames.filter(n => n !== name));
};

// Handle Enter key - add as text if no match selected
const handleEnter = () => {
    if (searchQuery.value.trim()) {
        // If there are search results and one is highlighted, add that
        // Otherwise, add as legacy name
        if (searchResults.value.length > 0) {
            addMember(searchResults.value[0]);
        } else {
            addLegacyName(searchQuery.value);
        }
    }
};

// Open quick create modal
const openQuickCreate = () => {
    quickCreateName.value = searchQuery.value;
    showQuickCreate.value = true;
    showDropdown.value = false;
};

// Close dropdown when clicking outside
const closeDropdown = () => {
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
};

// Get initials from name
const getInitials = (member) => {
    return (member.first_name?.[0] || '') + (member.last_name?.[0] || '');
};

// Combined display (members + legacy names)
const hasSelections = computed(() => {
    return props.modelValue.length > 0 || props.legacyNames.length > 0;
});
</script>

<template>
    <div class="relative">
        <!-- Search Input -->
        <div class="relative">
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Search family members or type a name..."
                class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white rounded-lg focus:ring-amber-500 focus:border-amber-500"
                @focus="showDropdown = searchQuery.length > 0"
                @blur="closeDropdown"
                @keydown.enter.prevent="handleEnter"
            />
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <div v-if="isSearching" class="absolute right-3 top-2.5">
                <svg class="animate-spin h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <!-- Dropdown Results -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 transform scale-95"
            enter-to-class="opacity-100 transform scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 transform scale-100"
            leave-to-class="opacity-0 transform scale-95"
        >
            <div
                v-if="showDropdown && (searchResults.length > 0 || searchQuery.length > 0)"
                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto"
            >
                <!-- Search Results -->
                <button
                    v-for="member in searchResults"
                    :key="member.id"
                    type="button"
                    @click="addMember(member)"
                    class="w-full flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                >
                    <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center text-amber-600 dark:text-amber-400 text-sm font-medium mr-3 overflow-hidden">
                        <img
                            v-if="member.photo_path"
                            :src="`/storage/${member.photo_path}`"
                            class="w-full h-full object-cover"
                        />
                        <span v-else>{{ getInitials(member) }}</span>
                    </div>
                    <div class="flex-1 text-left">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ member.display_name }}
                        </div>
                        <div v-if="member.nickname && member.nickname !== member.display_name" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ member.full_name }}
                        </div>
                    </div>
                </button>

                <!-- No results -->
                <div v-if="searchResults.length === 0 && !isSearching" class="px-4 py-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                        No family members found for "{{ searchQuery }}"
                    </p>
                    <div class="flex space-x-2">
                        <button
                            type="button"
                            @click="addLegacyName(searchQuery)"
                            class="text-sm text-amber-600 hover:text-amber-700 dark:text-amber-400"
                        >
                            Add as text
                        </button>
                        <span class="text-gray-300 dark:text-gray-600">|</span>
                        <Link
                            :href="route('family-members.create')"
                            class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400"
                        >
                            Create new member
                        </Link>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Selected Members & Names -->
        <div v-if="hasSelections" class="mt-3 flex flex-wrap gap-2">
            <!-- Family Member Chips -->
            <Link
                v-for="member in modelValue"
                :key="member.id"
                :href="route('family-members.show', member.id)"
                class="group inline-flex items-center px-3 py-1.5 rounded-full text-sm bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-900 transition"
                @click.stop
            >
                <div class="w-5 h-5 rounded-full bg-amber-200 dark:bg-amber-800 flex items-center justify-center text-xs font-medium mr-2 overflow-hidden">
                    <img
                        v-if="member.photo_path"
                        :src="`/storage/${member.photo_path}`"
                        class="w-full h-full object-cover"
                    />
                    <span v-else>{{ member.first_name?.[0] }}</span>
                </div>
                {{ member.display_name }}
                <button
                    type="button"
                    @click.prevent.stop="removeMember(member.id)"
                    class="ml-2 text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-200"
                >
                    &times;
                </button>
            </Link>

            <!-- Legacy Name Chips -->
            <span
                v-for="name in legacyNames"
                :key="name"
                class="inline-flex items-center px-3 py-1.5 rounded-full text-sm bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200"
            >
                {{ name }}
                <button
                    type="button"
                    @click="removeLegacyName(name)"
                    class="ml-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                >
                    &times;
                </button>
            </span>
        </div>

        <!-- Help Text -->
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Search for existing family members or type a name and press Enter to add
        </p>
    </div>
</template>
