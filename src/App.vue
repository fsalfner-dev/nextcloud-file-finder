<template>
    <NcContent app-name="filefinder">
        <NcAppNavigation>
            <template #list>
                <NcAppNavigationCaption name="Search for" is-heading />
                <SearchInput :modelValue="search_criteria.content" @update="onContentUpdate" @enter="onSubmit" label="Content of the file" />
                <SearchInput :modelValue="search_criteria.filename" @update="onFilenameUpdate" @enter="onSubmit" label="Filename (wildcards allowed)" />
                <NcAppNavigationCaption name="Filter File Types" is-heading />
                <FileTypeFilter :modelValue="search_criteria.file_types" @update:model-value="onFileTypeSelect" />
                <NcAppNavigationNew text="Search Files" @click="onSubmit" />
            </template>
        </NcAppNavigation>
        <NcAppContent>
            <template>
                <div id="maincontent">
                    <div v-if="contentState === contentStates.INITIAL" id="initial-state">
                        <p>Start a search by entering criteria in the navigation panel.</p>
                    </div>
                    <div v-else-if="contentState === contentStates.NO_RESULTS" id="no-results-state">
                        <h3>Search Results</h3>
                        <p>No files could be found matching your search criteria.</p>
                    </div>
                    <div v-else-if="contentState === contentStates.SHOW_RESULTS" id="results-state">
                        <div id="searchresult">
                            <h3>Search Result</h3>
                            <SearchFilelist 
                                :searchresult="search_result" 
                                :show_content="show_content_column"
                                :currentSort="search_sort"
                                :currentSortOrder="search_sort_order"
                                @update:sort="onSortUpdate"
                                @update:sortOrder="onSortOrderUpdate"
                            />
                        </div>
                        <div id="pagination">
                            <SearchPagination :searchresult="search_result" @update:page="onPageUpdate" @update:size="onSizeUpdate" />
                        </div>
                    </div>
                </div>
            </template>
        </NcAppContent>
    </NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import SearchInput from './components/SearchInput.vue'
import SearchFilelist from './components/SearchFilelist.vue'
import SearchPagination from './components/SearchPagination.vue'
import FileTypeFilter from './components/FileTypeFilter.vue'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

export default {
    name: 'App',
    data() {
        return {
            search_criteria: {
                content: '',
                filename: '',
                file_types: [],
            },
            search_pagination: {
                page: 0,
                size: 10,
            },
            search_sort: 'score',
            search_sort_order: 'desc',
            search_result: {
                hits: null,
                page: 0,
                size: 10,
                files: []
            },
            contentStates: {
                INITIAL: 'initial',
                NO_RESULTS: 'no_results',
                SHOW_RESULTS: 'show_results',
            },
            contentState: 'initial', // Default state
            show_content_column: false,
        }
    },
    components: {
        NcContent,
        NcAppContent,
        NcAppNavigation,
        NcAppNavigationNew,
        NcAppNavigationCaption,
        SearchInput,
        SearchFilelist,
        SearchPagination,
        FileTypeFilter,
    },
    methods: {
        onContentUpdate(e) {
            this.search_criteria.content = e;
        },

        onFilenameUpdate(e) {
            this.search_criteria.filename = e;
        },

        onFileTypeSelect(e) {
            this.search_criteria.file_types = e;
        },

        onPageUpdate(e) {
            this.search_pagination.page = e;
            this.performSearch();
        },

        onSizeUpdate(e) {
            this.search_pagination.size = e;
            this.performSearch();
        },

        onSortUpdate(e) {
            this.search_sort = e;
            this.search_pagination.page = 0;
            this.performSearch();
        },

        onSortOrderUpdate(e) {
            this.search_sort_order = e;
            this.search_pagination.page = 0;
            this.performSearch();
        },

        onSubmit() {
            this.performSearch();
        },

        performSearch() {
            const url = generateUrl('/apps/filefinder/search');
            if ((this.search_criteria.content === '') && (this.search_sort === 'score')){
                this.search_sort = 'path';
                this.search_sort_order = 'asc';
            }
            const params = {
                search_criteria: this.search_criteria,
                size: this.search_pagination.size,
                page: this.search_pagination.page,
                sort: this.search_sort,
                sort_order: this.search_sort_order,
            };
            axios.get(url, { params: params })
                .then((response) => {
                    this.search_result.hits = response.data.hits;
                    this.search_result.page = response.data.page;
                    this.search_result.size = response.data.size;
                    this.search_result.files = response.data.files.map(file => ({
                        content_type: file.content_type,
                        name: file.name,
                        highlights: file.highlights,
                        link: file.link,
                        icon_link: file.icon_link,
                        modified: new Date(file.modified_at * 1000).toLocaleString(),
                    }));

                    // Update content state based on results
                    if (this.search_result.hits === 0) {
                        this.contentState = this.contentStates.NO_RESULTS;
                    } else {
                        this.contentState = this.contentStates.SHOW_RESULTS;
                    }
                    this.show_content_column = this.search_criteria.content !== '';
                })
                .catch((error) => {
                    showError(error.response.data.error_message);
                    console.error(error);
                });
        }
    }
}
</script>

<style scoped lang="scss">
#maincontent {
    padding: 16px;
}

#initial-state {
    text-align: center;
    font-style: italic;
    color: #666;
}

#no-results-state p {
    font-style: italic;
    color: #666;
}

#searchresult {
    padding: 16px;
}

#pagination {
    padding: 16px;
    margin-bottom: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
}
</style>
