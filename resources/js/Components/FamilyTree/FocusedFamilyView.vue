<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    members: Array,
    relationshipTypes: Object,
    initialFocusId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['select']);

// Track the focused member and navigation history
const focusedMemberId = ref(props.initialFocusId || props.members[0]?.id || null);
const navigationHistory = ref([]);

// The currently focused member with full data
const focusedMember = computed(() => {
    return props.members.find(m => m.id === focusedMemberId.value);
});

// Group immediate family by specific relationship type
const immediateFamily = computed(() => {
    if (!focusedMember.value) return { father: null, mother: null, stepParents: [], spouses: [], siblings: [], children: [] };

    const getMemberById = (id) => props.members.find(m => m.id === id);

    // Get all parents and filter by gender/relationship type
    const allParents = (focusedMember.value.parents || []).map(p => ({
        ...getMemberById(p.id),
        relationshipType: p.pivot?.relationship_type,
    })).filter(Boolean);

    // Father = male parent (not step)
    const father = allParents.find(p => p.gender === 'male' && !p.relationshipType?.includes('step'));

    // Mother = female parent (not step)
    const mother = allParents.find(p => p.gender === 'female' && !p.relationshipType?.includes('step'));

    // Step-parents = any parent with 'step' in relationship type
    const stepParents = allParents.filter(p =>
        p.relationshipType?.includes('step') ||
        (p.gender === 'male' && p.id !== father?.id) ||
        (p.gender === 'female' && p.id !== mother?.id)
    );

    return {
        father,
        mother,
        stepParents,
        spouses: (focusedMember.value.spouses || []).map(s => getMemberById(s.id)).filter(Boolean),
        siblings: (focusedMember.value.siblings || []).map(s => ({
            ...getMemberById(s.id),
            relationshipType: s.pivot?.relationship_type,
        })).filter(Boolean),
        children: (focusedMember.value.children || []).map(c => getMemberById(c.id)).filter(Boolean),
    };
});

// Extended family (not in immediate)
const extendedFamily = computed(() => {
    if (!focusedMember.value?.all_related_members) return [];

    const immediateIds = new Set([
        immediateFamily.value.father?.id,
        immediateFamily.value.mother?.id,
        ...immediateFamily.value.stepParents.map(m => m.id),
        ...immediateFamily.value.spouses.map(m => m.id),
        ...immediateFamily.value.siblings.map(m => m.id),
        ...immediateFamily.value.children.map(m => m.id),
    ].filter(Boolean));

    return focusedMember.value.all_related_members
        .filter(r => !immediateIds.has(r.id))
        .map(r => ({
            ...props.members.find(m => m.id === r.id),
            relationshipType: r.pivot?.relationship_type,
        }))
        .filter(m => m.id);
});

// Format member display with full details
const formatMemberDisplay = (member, relationship = null) => {
    if (!member) return '';
    let display = member.full_name || `${member.first_name} ${member.last_name || ''}`.trim();
    if (member.nickname && member.nickname !== display) {
        display += ` [${member.nickname}]`;
    }
    if (relationship) {
        display += ` (${relationship})`;
    }
    return display;
};

// Count of further connections for each family member
const getConnectionCount = (member) => {
    const m = props.members.find(p => p.id === member.id);
    return m?.all_related_members?.length || 0;
};

// Get relationship type label
const getTypeLabel = (slug) => {
    const allTypes = [
        ...(props.relationshipTypes?.immediate || []),
        ...(props.relationshipTypes?.extended || []),
        ...(props.relationshipTypes?.non_family || []),
    ];
    const type = allTypes.find(t => t.slug === slug);
    return type?.label || slug;
};

// Navigate to focus on a different member
const focusOn = (member) => {
    if (member.id === focusedMemberId.value) return;

    // Add current to history
    navigationHistory.value.push(focusedMemberId.value);

    // Limit history size
    if (navigationHistory.value.length > 10) {
        navigationHistory.value.shift();
    }

    focusedMemberId.value = member.id;
    emit('select', member);
};

// Go back in history
const goBack = () => {
    if (navigationHistory.value.length > 0) {
        focusedMemberId.value = navigationHistory.value.pop();
    }
};

// Get breadcrumb trail
const breadcrumbs = computed(() => {
    return navigationHistory.value.map(id => props.members.find(m => m.id === id)).filter(Boolean);
});

// Category styling
const categoryStyles = {
    parents: { bg: 'bg-blue-500', ring: 'ring-blue-400', label: 'Parents' },
    spouses: { bg: 'bg-pink-500', ring: 'ring-pink-400', label: 'Spouse' },
    siblings: { bg: 'bg-green-500', ring: 'ring-green-400', label: 'Siblings' },
    children: { bg: 'bg-amber-500', ring: 'ring-amber-400', label: 'Children' },
    extended: { bg: 'bg-purple-500', ring: 'ring-purple-400', label: 'Extended' },
};

// Watch for external focus changes
watch(() => props.initialFocusId, (newId) => {
    if (newId && newId !== focusedMemberId.value) {
        focusedMemberId.value = newId;
    }
});
</script>

<template>
    <div class="focused-family-view">
        <!-- Navigation Breadcrumbs -->
        <div v-if="breadcrumbs.length > 0" class="flex items-center gap-2 mb-6 text-sm">
            <button
                @click="goBack"
                class="flex items-center gap-1 px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-full transition"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back
            </button>
            <div class="flex items-center gap-1 text-gray-500">
                <template v-for="(crumb, index) in breadcrumbs.slice(-3)" :key="crumb.id">
                    <button
                        @click="focusOn(crumb)"
                        class="hover:text-amber-400 transition"
                    >
                        {{ crumb.first_name }}
                    </button>
                    <span class="text-gray-600">/</span>
                </template>
                <span class="text-amber-400 font-medium">{{ focusedMember?.first_name }}</span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="relative min-h-[500px]">
            <!-- Focused Member (Center) -->
            <div v-if="focusedMember" class="flex flex-col items-center mb-12">
                <div class="relative">
                    <!-- Glow effect -->
                    <div class="absolute inset-0 rounded-full bg-amber-500/30 blur-xl scale-150"></div>

                    <!-- Main avatar -->
                    <div
                        class="relative w-32 h-32 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white text-4xl font-bold shadow-xl ring-4 ring-amber-400/50"
                    >
                        {{ focusedMember.first_name?.[0] }}{{ focusedMember.last_name?.[0] || '' }}
                    </div>
                </div>

                <h2 class="mt-4 text-2xl font-bold text-white">{{ focusedMember.display_name }}</h2>
                <p v-if="focusedMember.full_name !== focusedMember.display_name" class="text-gray-400">
                    {{ focusedMember.full_name }}
                </p>
                <div v-if="focusedMember.birth_date" class="text-sm text-gray-500 mt-1">
                    {{ focusedMember.is_living ? `${focusedMember.age} years old` : `${focusedMember.age} years` }}
                </div>

                <Link
                    :href="route('family-members.show', focusedMember.id)"
                    class="mt-3 text-sm text-amber-400 hover:text-amber-300 transition"
                >
                    View full profile
                </Link>
            </div>

            <!-- Family Categories Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Father -->
                <div v-if="immediateFamily.father" class="family-category">
                    <h3 class="text-sm font-medium text-blue-400 mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        Father
                    </h3>
                    <button
                        @click="focusOn(immediateFamily.father)"
                        class="w-full flex items-center gap-3 p-3 bg-gray-800/50 hover:bg-gray-700/50 rounded-xl transition group"
                    >
                        <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium">
                            {{ immediateFamily.father.first_name?.[0] }}{{ immediateFamily.father.last_name?.[0] || '' }}
                        </div>
                        <div class="flex-1 text-left">
                            <div class="text-white font-medium group-hover:text-amber-400 transition">
                                {{ immediateFamily.father.full_name }}
                            </div>
                            <div v-if="immediateFamily.father.nickname" class="text-xs text-blue-400">
                                "{{ immediateFamily.father.nickname }}"
                            </div>
                            <div v-if="getConnectionCount(immediateFamily.father) > 1" class="text-xs text-gray-500">
                                {{ getConnectionCount(immediateFamily.father) }} connections
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-amber-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Mother -->
                <div v-if="immediateFamily.mother" class="family-category">
                    <h3 class="text-sm font-medium text-rose-400 mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        Mother
                    </h3>
                    <button
                        @click="focusOn(immediateFamily.mother)"
                        class="w-full flex items-center gap-3 p-3 bg-gray-800/50 hover:bg-gray-700/50 rounded-xl transition group"
                    >
                        <div class="w-12 h-12 rounded-full bg-rose-500 flex items-center justify-center text-white font-medium">
                            {{ immediateFamily.mother.first_name?.[0] }}{{ immediateFamily.mother.last_name?.[0] || '' }}
                        </div>
                        <div class="flex-1 text-left">
                            <div class="text-white font-medium group-hover:text-amber-400 transition">
                                {{ immediateFamily.mother.full_name }}
                            </div>
                            <div v-if="immediateFamily.mother.nickname" class="text-xs text-rose-400">
                                "{{ immediateFamily.mother.nickname }}"
                            </div>
                            <div v-if="getConnectionCount(immediateFamily.mother) > 1" class="text-xs text-gray-500">
                                {{ getConnectionCount(immediateFamily.mother) }} connections
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-amber-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Step-Parents -->
                <div v-if="immediateFamily.stepParents.length > 0" class="family-category">
                    <h3 class="text-sm font-medium text-indigo-400 mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        Step-Parent{{ immediateFamily.stepParents.length > 1 ? 's' : '' }}
                    </h3>
                    <div class="space-y-2">
                        <button
                            v-for="member in immediateFamily.stepParents"
                            :key="member.id"
                            @click="focusOn(member)"
                            class="w-full flex items-center gap-3 p-3 bg-gray-800/50 hover:bg-gray-700/50 rounded-xl transition group"
                        >
                            <div class="w-12 h-12 rounded-full bg-indigo-500 flex items-center justify-center text-white font-medium">
                                {{ member.first_name?.[0] }}{{ member.last_name?.[0] || '' }}
                            </div>
                            <div class="flex-1 text-left">
                                <div class="text-white font-medium group-hover:text-amber-400 transition">
                                    {{ member.full_name }}
                                </div>
                                <div class="text-xs text-indigo-400">
                                    {{ member.gender === 'male' ? 'Stepfather' : 'Stepmother' }}
                                    <span v-if="member.nickname"> "{{ member.nickname }}"</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-500 group-hover:text-amber-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Spouse(s) -->
                <div v-if="immediateFamily.spouses.length > 0" class="family-category">
                    <h3 class="text-sm font-medium text-pink-400 mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-pink-500"></span>
                        Spouse
                    </h3>
                    <div class="space-y-2">
                        <button
                            v-for="member in immediateFamily.spouses"
                            :key="member.id"
                            @click="focusOn(member)"
                            class="w-full flex items-center gap-3 p-3 bg-gray-800/50 hover:bg-gray-700/50 rounded-xl transition group"
                        >
                            <div class="w-12 h-12 rounded-full bg-pink-500 flex items-center justify-center text-white font-medium">
                                {{ member.first_name?.[0] }}{{ member.last_name?.[0] || '' }}
                            </div>
                            <div class="flex-1 text-left">
                                <div class="text-white font-medium group-hover:text-amber-400 transition">
                                    {{ member.display_name }}
                                </div>
                                <div v-if="getConnectionCount(member) > 1" class="text-xs text-gray-500">
                                    {{ getConnectionCount(member) }} connections
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-500 group-hover:text-amber-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Siblings -->
                <div v-if="immediateFamily.siblings.length > 0" class="family-category">
                    <h3 class="text-sm font-medium text-green-400 mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        Siblings
                    </h3>
                    <div class="space-y-2">
                        <button
                            v-for="member in immediateFamily.siblings"
                            :key="member.id"
                            @click="focusOn(member)"
                            class="w-full flex items-center gap-3 p-3 bg-gray-800/50 hover:bg-gray-700/50 rounded-xl transition group"
                        >
                            <div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center text-white font-medium">
                                {{ member.first_name?.[0] }}{{ member.last_name?.[0] || '' }}
                            </div>
                            <div class="flex-1 text-left">
                                <div class="text-white font-medium group-hover:text-amber-400 transition">
                                    {{ member.full_name }}
                                </div>
                                <div class="text-xs text-green-400">
                                    {{ member.gender === 'male' ? 'Brother' : 'Sister' }}
                                    <span v-if="member.nickname"> "{{ member.nickname }}"</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-500 group-hover:text-amber-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Children -->
                <div v-if="immediateFamily.children.length > 0" class="family-category">
                    <h3 class="text-sm font-medium text-amber-400 mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Children
                    </h3>
                    <div class="space-y-2">
                        <button
                            v-for="member in immediateFamily.children"
                            :key="member.id"
                            @click="focusOn(member)"
                            class="w-full flex items-center gap-3 p-3 bg-gray-800/50 hover:bg-gray-700/50 rounded-xl transition group"
                        >
                            <div class="w-12 h-12 rounded-full bg-amber-500 flex items-center justify-center text-white font-medium">
                                {{ member.first_name?.[0] }}{{ member.last_name?.[0] || '' }}
                            </div>
                            <div class="flex-1 text-left">
                                <div class="text-white font-medium group-hover:text-amber-400 transition">
                                    {{ member.display_name }}
                                </div>
                                <div v-if="getConnectionCount(member) > 1" class="text-xs text-gray-500">
                                    {{ getConnectionCount(member) }} connections
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-500 group-hover:text-amber-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Extended Family Section -->
            <div v-if="extendedFamily.length > 0" class="mt-8 pt-8 border-t border-gray-700">
                <h3 class="text-sm font-medium text-purple-400 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                    Extended Family
                    <span class="text-gray-500 font-normal">({{ extendedFamily.length }})</span>
                </h3>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="member in extendedFamily"
                        :key="member.id"
                        @click="focusOn(member)"
                        class="flex items-center gap-2 px-3 py-2 bg-gray-800/50 hover:bg-purple-900/30 rounded-lg transition group"
                    >
                        <div class="w-8 h-8 rounded-full bg-purple-500/50 flex items-center justify-center text-white text-sm">
                            {{ member.first_name?.[0] }}
                        </div>
                        <span class="text-gray-300 group-hover:text-white transition">
                            {{ member.display_name }}
                        </span>
                        <span v-if="member.relationshipType" class="text-xs text-purple-400">
                            {{ getTypeLabel(member.relationshipType) }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Empty State for No Relationships -->
            <div
                v-if="focusedMember && !immediateFamily.father && !immediateFamily.mother && immediateFamily.stepParents.length === 0 && immediateFamily.spouses.length === 0 && immediateFamily.siblings.length === 0 && immediateFamily.children.length === 0 && extendedFamily.length === 0"
                class="text-center py-12 text-gray-500"
            >
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <p class="mb-2">No family connections yet</p>
                <Link
                    :href="route('family-members.edit', focusedMember.id)"
                    class="text-amber-400 hover:text-amber-300"
                >
                    Add relationships
                </Link>
            </div>
        </div>

        <!-- Quick Jump to Other Members -->
        <div class="mt-8 pt-6 border-t border-gray-700">
            <details class="group">
                <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-400 transition flex items-center gap-2">
                    <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    Jump to any family member ({{ members.length }} total)
                </summary>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="member in members"
                        :key="member.id"
                        @click="focusOn(member)"
                        :class="[
                            'px-3 py-1.5 rounded-full text-sm transition',
                            member.id === focusedMemberId
                                ? 'bg-amber-500 text-white'
                                : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                        ]"
                    >
                        {{ member.display_name }}
                    </button>
                </div>
            </details>
        </div>
    </div>
</template>

<style scoped>
.focused-family-view {
    @apply p-6;
}

.family-category {
    @apply p-4 bg-gray-800/30 rounded-xl border border-gray-700/50;
}
</style>
