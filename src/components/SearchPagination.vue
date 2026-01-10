<template>
    <div class="search-pagination" v-if="searchresult.hits > 0">
        <div class="pagination-controls">
            <!-- First Page Button -->
            <NcButton
                :disabled="isFirstPage"
                variant="secondary"
                @click="goToPage(0)"
            >
                <template #icon>
				    <PageFirst :size="20" />
			    </template>
            </NcButton>

            <!-- Previous Page Button -->
            <NcButton
                :disabled="isFirstPage"
                variant="secondary"
                @click="goToPage(searchresult.page - 1)"
            >
                <template #icon>
				    <ChevronLeft :size="20" />
			    </template>
            </NcButton>

            <!-- Page Info -->
            <span class="page-info">
                Page {{ searchresult.page + 1 }} of {{ totalPages }}
            </span>

            <!-- Next Page Button -->
            <NcButton
                :disabled="isLastPage"
                variant="secondary"
                @click="goToPage(searchresult.page + 1)"
            >
                <template #icon>
				    <ChevronRight :size="20" />
			    </template>
            </NcButton>

            <!-- Last Page Button -->
            <NcButton
                :disabled="isLastPage"
                variant="secondary"
                @click="goToPage(totalPages - 1)"
            >
                <template #icon>
				    <PageLast :size="20" />
			    </template>
            </NcButton>
        </div>

        <div class="page-size-controls">
            <label for="page-size-select">Page Size:</label>
            <select id="page-size-select" v-model="selectedPageSize" @change="updatePageSize">
                <option v-for="size in pageSizes" :key="size" :value="size">
                    {{ size }}
                </option>
            </select>
        </div>
    </div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import PageFirst from 'vue-material-design-icons/PageFirst.vue'
import PageLast from 'vue-material-design-icons/PageLast.vue'

export default {
    name: 'SearchPagination',
    props: {
        searchresult: {
            type: Object,
            required: true,
        },
    },
    components: {
        NcButton,
        ChevronLeft,
        ChevronRight,
        PageFirst,
        PageLast,
    },
    data() {
        return {
            pageSizes: [5, 10, 50, 100], 
            selectedPageSize: this.searchresult.size, // Currently selected page size
        }
    },
    computed: {
        totalPages() {
            return Math.ceil(this.searchresult.hits / this.searchresult.size)
        },
        isFirstPage() {
            return this.searchresult.page === 0
        },
        isLastPage() {
            return this.searchresult.page === this.totalPages - 1
        },
    },
    methods: {
        goToPage(page) {
            if (page >= 0 && page < this.totalPages) {
                this.$emit('update:page', page) // Emit event to update the page
            }
        },
        updatePageSize() {
            this.$emit('update:size', this.selectedPageSize) // Emit event to update the page size
        },
    },
}
</script>

<style scoped>
.search-pagination {
    width: 100%;
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: center;
    gap: 16px;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 16px;
}

.page-info {
    font-weight: normal;
}

.page-size-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>