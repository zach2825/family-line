<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Use explicit props + emit for better compatibility with Inertia forms
const props = defineProps({
    familyMembers: {
        type: Array,
        default: () => [],
    },
    modelValue: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

// Use a local ref for selected IDs - more reliable reactivity than computed with array props
const localSelectedIds = ref([...(props.modelValue || [])]);

// Sync from parent to local (when prop changes externally)
watch(() => props.modelValue, (newVal) => {
    const newIds = newVal || [];
    // Only update if actually different to avoid infinite loops
    if (JSON.stringify(newIds) !== JSON.stringify(localSelectedIds.value)) {
        localSelectedIds.value = [...newIds];
    }
}, { immediate: true, deep: true });

// Computed for easy get/set access
const selectedIds = computed({
    get: () => localSelectedIds.value,
    set: (val) => {
        localSelectedIds.value = val;
        emit('update:modelValue', val);
    },
});

const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showDropdown = ref(false);
const highlightedIndex = ref(-1);
const inputRef = ref(null);
const dropdownRef = ref(null);

// Keep a local cache of members found via search (for display)
const memberCache = ref(new Map());

// Quick-create modal
const showCreateModal = ref(false);
const createForm = ref({
    first_name: '',
    last_name: '',
    nickname: '',
    gender: '',
});
const createErrors = ref({});
const isCreating = ref(false);

// Get selected members with full data - check both familyMembers prop and cache
const selectedMembers = computed(() => {
    return selectedIds.value.map(id => {
        // First try to find in familyMembers prop
        let member = props.familyMembers.find(m => m.id === id);
        // Fall back to cache (for members added via search/create that aren't in initial prop)
        if (!member && memberCache.value.has(id)) {
            member = memberCache.value.get(id);
        }
        return member || { id, display_name: 'Unknown', first_name: '?' };
    });
});

// Filter results to exclude already selected members
const filteredResults = computed(() => {
    return searchResults.value.filter(member => !selectedIds.value.includes(member.id));
});

// Check if we should show "create new" option
const showCreateOption = computed(() => {
    return searchQuery.value.trim().length > 0 && filteredResults.value.length === 0;
});

// Search family members
let searchTimeout = null;
const search = async () => {
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        showDropdown.value = false;
        return;
    }

    isSearching.value = true;

    try {
        const response = await axios.get(route('api.family-members.search'), {
            params: { q: searchQuery.value },
        });
        const members = response.data.members || [];
        // Cache all search results for display purposes
        members.forEach(m => memberCache.value.set(m.id, m));
        searchResults.value = members;
        showDropdown.value = true;
        highlightedIndex.value = -1;
    } catch (err) {
        console.error('Search error:', err);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

// Debounced search
watch(searchQuery, (newVal) => {
    clearTimeout(searchTimeout);
    if (newVal && newVal.trim()) {
        searchTimeout = setTimeout(search, 300);
    } else {
        searchResults.value = [];
        showDropdown.value = false;
    }
});

// Select a member
const selectMember = (member) => {
    if (!selectedIds.value.includes(member.id)) {
        // Cache the member for display purposes
        memberCache.value.set(member.id, member);
        // Create new array and emit to parent
        selectedIds.value = [...selectedIds.value, member.id];
    }
    searchQuery.value = '';
    showDropdown.value = false;
    highlightedIndex.value = -1;
    inputRef.value?.focus();
};

// Remove a member
const removeMember = (memberId) => {
    // Create new array and emit to parent
    selectedIds.value = selectedIds.value.filter(id => id !== memberId);
};

// Keyboard navigation
const onKeyDown = (e) => {
    const results = filteredResults.value;
    const hasCreateOption = showCreateOption.value;
    const totalItems = results.length + (hasCreateOption ? 1 : 0);

    if (!showDropdown.value && searchQuery.value.trim()) {
        if (e.key === 'ArrowDown' || e.key === 'Enter') {
            showDropdown.value = true;
            return;
        }
    }

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            highlightedIndex.value = Math.min(highlightedIndex.value + 1, totalItems - 1);
            break;
        case 'ArrowUp':
            e.preventDefault();
            highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1);
            break;
        case 'Enter':
            e.preventDefault();
            if (highlightedIndex.value >= 0) {
                if (highlightedIndex.value < results.length) {
                    selectMember(results[highlightedIndex.value]);
                } else if (hasCreateOption) {
                    openCreateModal();
                }
            } else if (hasCreateOption) {
                openCreateModal();
            }
            break;
        case 'Escape':
            showDropdown.value = false;
            highlightedIndex.value = -1;
            break;
    }
};

// Open create modal with pre-filled name
const openCreateModal = () => {
    const parts = searchQuery.value.trim().split(/\s+/);
    createForm.value = {
        first_name: parts[0] || '',
        last_name: parts.slice(1).join(' ') || '',
        nickname: '',
        gender: '',
    };
    createErrors.value = {};
    showCreateModal.value = true;
    showDropdown.value = false;
};

// Create new family member
const createMember = async () => {
    isCreating.value = true;
    createErrors.value = {};

    try {
        const response = await axios.post(route('family-members.store'), {
            first_name: createForm.value.first_name,
            last_name: createForm.value.last_name || null,
            nickname: createForm.value.nickname || null,
            gender: createForm.value.gender || null,
            is_living: true,
        });

        // Add to our cache and select the new member
        const newMember = response.data.member;
        if (newMember) {
            // Cache for display
            memberCache.value.set(newMember.id, newMember);
            // Add to selection
            selectedIds.value = [...selectedIds.value, newMember.id];
        }

        showCreateModal.value = false;
        searchQuery.value = '';
    } catch (err) {
        if (err.response?.status === 422) {
            createErrors.value = err.response.data.errors || {};
        } else {
            console.error('Create error:', err);
            createErrors.value = { general: 'Failed to create family member' };
        }
    } finally {
        isCreating.value = false;
    }
};

// Close dropdown on outside click
const handleOutsideClick = (e) => {
    const isOutsideDropdown = dropdownRef.value && !dropdownRef.value.contains(e.target);
    const isOutsideInput = inputRef.value && !inputRef.value.$el?.contains(e.target);

    if (isOutsideDropdown && isOutsideInput) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
    clearTimeout(searchTimeout);
});

// Get initials for avatar
const getInitials = (member) => {
    const first = member.first_name?.[0] || '';
    const last = member.last_name?.[0] || '';
    return (first + last).toUpperCase() || '?';
};
</script>

<template>
    <div class="relative">
        <!-- Selected Members as Chips -->
        <div v-if="selectedMembers.length" class="flex flex-wrap gap-2 mb-2">
            <span
                v-for="member in selectedMembers"
                :key="member.id"
                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200"
            >
                <span
                    v-if="member.photo_path"
                    class="w-5 h-5 rounded-full overflow-hidden mr-2 flex-shrink-0"
                >
                    <img :src="`/storage/${member.photo_path}`" class="w-full h-full object-cover" />
                </span>
                <span
                    v-else
                    class="w-5 h-5 rounded-full bg-amber-200 dark:bg-amber-800 flex items-center justify-center text-xs font-medium mr-2 flex-shrink-0"
                >
                    {{ getInitials(member) }}
                </span>
                {{ member.display_name || member.first_name }}
                <button
                    type="button"
                    @click="removeMember(member.id)"
                    class="ml-2 text-amber-600 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-100"
                >
                    &times;
                </button>
            </span>
        </div>

        <!-- Search Input -->
        <div class="relative">
            <TextInput
                ref="inputRef"
                v-model="searchQuery"
                type="text"
                class="w-full"
                placeholder="Search family members..."
                @keydown="onKeyDown"
                @focus="searchQuery && (showDropdown = true)"
            />
            <div v-if="isSearching" class="absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <!-- Dropdown -->
        <div
            v-if="showDropdown && (filteredResults.length > 0 || showCreateOption)"
            ref="dropdownRef"
            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-auto"
        >
            <!-- Search Results -->
            <button
                v-for="(member, index) in filteredResults"
                :key="member.id"
                type="button"
                @click="selectMember(member)"
                :class="[
                    'w-full flex items-center px-4 py-2 text-left transition',
                    highlightedIndex === index
                        ? 'bg-amber-100 dark:bg-amber-900/50'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-700'
                ]"
            >
                <span
                    v-if="member.photo_path"
                    class="w-8 h-8 rounded-full overflow-hidden mr-3 flex-shrink-0"
                >
                    <img :src="`/storage/${member.photo_path}`" class="w-full h-full object-cover" />
                </span>
                <span
                    v-else
                    class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center text-sm font-medium text-amber-600 dark:text-amber-400 mr-3 flex-shrink-0"
                >
                    {{ getInitials(member) }}
                </span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ member.display_name || `${member.first_name} ${member.last_name || ''}`.trim() }}
                    </div>
                    <div v-if="member.nickname && member.nickname !== member.first_name" class="text-xs text-gray-500 dark:text-gray-400">
                        "{{ member.nickname }}"
                    </div>
                </div>
            </button>

            <!-- Create New Option -->
            <button
                v-if="showCreateOption"
                type="button"
                @click="openCreateModal"
                :class="[
                    'w-full flex items-center px-4 py-3 text-left border-t border-gray-200 dark:border-gray-700 transition',
                    highlightedIndex === filteredResults.length
                        ? 'bg-green-100 dark:bg-green-900/30'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-700'
                ]"
            >
                <span class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mr-3 flex-shrink-0">
                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </span>
                <div>
                    <div class="text-sm font-medium text-green-700 dark:text-green-300">
                        Create "{{ searchQuery }}" as new family member
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Add to your family tree
                    </div>
                </div>
            </button>
        </div>

        <!-- Quick Create Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Add New Family Member
                </h2>

                <div v-if="createErrors.general" class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-md text-sm">
                    {{ createErrors.general }}
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="create_first_name" value="First Name *" />
                            <TextInput
                                id="create_first_name"
                                v-model="createForm.first_name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="createErrors.first_name?.[0]" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="create_last_name" value="Last Name" />
                            <TextInput
                                id="create_last_name"
                                v-model="createForm.last_name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="createErrors.last_name?.[0]" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="create_nickname" value="Nickname (optional)" />
                        <TextInput
                            id="create_nickname"
                            v-model="createForm.nickname"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., Mom, Grandpa Joe, etc."
                        />
                        <InputError :message="createErrors.nickname?.[0]" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="create_gender" value="Gender (optional)" />
                        <select
                            id="create_gender"
                            v-model="createForm.gender"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm"
                        >
                            <option value="">Not specified</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showCreateModal = false">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        @click="createMember"
                        :disabled="isCreating || !createForm.first_name"
                        class="bg-amber-600 hover:bg-amber-700"
                    >
                        <svg v-if="isCreating" class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Add Family Member
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
