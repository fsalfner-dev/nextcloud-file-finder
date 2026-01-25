<template>
    <div class="search-sort">
        <label for="sort-select">Sort by:</label>
        <select id="sort-select" v-model="selectedSort" @change="updateSort">
            <option value="score">Content match score</option>
            <option value="modified">Modification date</option>
            <option value="path">File path</option>
        </select>
        <label for="sort-order-select" v-if="selectedSort !== 'score'">Order:</label>
        <select id="sort-order-select" v-if="selectedSort !== 'score'" v-model="selectedSortOrder" @change="updateSortOrder">
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
        </select>
    </div>
</template>

<script>
export default {
    name: 'SearchSort',
    props: {
        modelValue: {
            type: String,
            default: 'score'
        },
        sortOrder: {
            type: String,
            default: 'desc'
        },
    },
    emits: ['update:modelValue', 'update:sortOrder'],
    data() {
        return {
            selectedSort: this.modelValue,
            selectedSortOrder: this.sortOrder,
        }
    },
    watch: {
        modelValue(newValue) {
            this.selectedSort = newValue;
        },
        sortOrder(newValue) {
            this.selectedSortOrder = newValue;
        }
    },
    methods: {
        updateSort() {
            this.$emit('update:modelValue', this.selectedSort);
        },
        updateSortOrder() {
            this.$emit('update:sortOrder', this.selectedSortOrder);
        },
    },
}
</script>

<style scoped>
.search-sort {
    display: flex;
    align-items: center;
    gap: 8px;
}

#sort-select,
#sort-order-select {
    padding: 4px 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background-color: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 14px;
    cursor: pointer;
}

#sort-select:hover,
#sort-order-select:hover {
    border-color: var(--color-primary-element);
}

#sort-select:focus,
#sort-order-select:focus {
    outline: none;
    border-color: var(--color-primary-element);
}
</style>
