<!--

SPDX-FileCopyrightText: 2026 Felix Salfner
SPDX-License-Identifier: AGPL-3.0-or-later

The app's main component.
-->
<template>
    <NcContent app-name="filefinder">
        <NcAppNavigation>
            <template #list>
                <NcAppNavigationCaption :name="t('filefinder', 'Search for')" is-heading />
                <SearchInput v-if="initial_state.fulltextsearch_available" 
                    :modelValue="search_criteria.content" 
                    @update="onContentUpdate" 
                    @enter="onSubmit" 
                    :label="t('filefinder', 'Content of the file')" />
                <SearchInput 
                    :modelValue="search_criteria.filename" 
                    @update="onFilenameUpdate" 
                    @enter="onSubmit" 
                    :label="t('filefinder', 'Filename (wildcards allowed)')" />
                <NcAppNavigationNew :text="t('filefinder', 'Search Files')" @click="onSubmit" />
                <NcAppNavigationCaption :name="t('filefinder', 'Filter results')" is-heading />
                <NcAppNavigationItem :name="t('filefinder', 'File Type Filter')" :allowCollapse="true">
                    <template #icon>
					    <IconFileQuestionOutline :size="20" />
				    </template>
                    <template #counter>
                        <NcCounterBubble v-if="search_criteria.file_types.length > 0" :count="search_criteria.file_types.length" />
                    </template>
                    <template #default>
                        <FileTypeFilter :modelValue="search_criteria.file_types" @update:model-value="onFileTypeSelect" />
                    </template>
                </NcAppNavigationItem>
                <NcAppNavigationItem :name="t('filefinder', 'Date Filter')" :allowCollapse="true">
                    <template #icon>
					    <IconCalendarMonthOutline :size="20" />
				    </template>
                    <template #counter>
                        <NcCounterBubble v-if="getNoOfDateFilters() > 0" :count="getNoOfDateFilters()" />
                    </template>
                    <template #default>
                        <DateFilter :modelValue="search_criteria.after_date" @update:model-value="onAfterDateSelect" dateType="after" />
                        <DateFilter :modelValue="search_criteria.before_date" @update:model-value="onBeforeDateSelect" dateType="before"/>
                    </template>
                </NcAppNavigationItem>
                <NcAppNavigationItem :name="t('filefinder', 'Excluded Folders')" :allowCollapse="true">
                    <template #icon>
					    <IconFolderCancelOutline :size="20" />
				    </template>
                    <template #counter>
                        <NcCounterBubble v-if="search_criteria.exclude_folders.length > 0" :count="search_criteria.exclude_folders.length" />
                    </template>
                    <template #default>
                        <ExcludeFoldersFilter :modelValue="search_criteria.exclude_folders" @update:model-value="removeExcludeFolder" />
                    </template>
                </NcAppNavigationItem>
                <NcAppNavigationItem :name="t('filefinder', 'Start Folder')" :allowCollapse="true">
                    <template #icon>
					    <IconFolderSearchOutline :size="20" />
				    </template>
                    <template #counter>
                        <NcCounterBubble v-if="search_criteria.start_folder" :count="1" />
                    </template>
                    <template #default>
                        <FolderDrilldownFilter :modelValue="search_criteria.start_folder" @update:model-value="removeStartFolder" />
                    </template>
                </NcAppNavigationItem>
            </template>
        </NcAppNavigation>
        <NcAppContent>
            <template>
                <div id="maincontent">
                    <div v-if="contentState === contentStates.INITIAL" id="initial-state">
                        <p>{{ t('filefinder', 'Start a search by entering criteria in the navigation panel.') }}</p>
                    </div>
                    <div v-else-if="contentState === contentStates.NO_RESULTS" id="no-results-state">
                        <h3>{{ t('filefinder', 'Search Result') }}</h3>
                        <p>{{ t('filefinder', 'No files could be found matching your search criteria.') }}</p>
                    </div>
                    <div v-else-if="contentState === contentStates.LOADING" id="no-results-state">
                        <h3>{{ t('filefinder', 'Search Result') }}</h3>
                        <div class="loading-indicator">
                            <NcLoadingIcon :size="48" />
                        </div>
                    </div>
                    <div v-else-if="contentState === contentStates.SHOW_RESULTS" id="results-state">
                        <div id="searchresult">
                            <h3>{{ t('filefinder', 'Search Result') }}</h3>
                            <SearchFilelist 
                                :files="search_result.files" 
                                :show_content="show_content_column"
                                :currentSort="search_sort"
                                :currentSortOrder="search_sort_order"
                                @update:sort="onSortUpdate"
                                @update:sortOrder="onSortOrderUpdate"
                                @excludeFolder="addExcludedFolder"
                                @folderDrilldown="setStartFolder"
                            />
                        </div>
                        <div id="pagination">
                            <SearchPagination 
                                :size="search_result.size"
                                :page="search_result.page"
                                :hits="search_result.hits"
                                :totalHitsAvailable="initial_state.fulltextsearch_available" 
                                @update:page="onPageUpdate" 
                                @update:size="onSizeUpdate" />
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
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import SearchInput from './components/SearchInput.vue'
import SearchFilelist from './components/SearchFilelist.vue'
import SearchPagination from './components/SearchPagination.vue'
import DateFilter from './components/DateFilter.vue'
import FileTypeFilter from './components/FileTypeFilter.vue'
import { generateUrl } from '@nextcloud/router'
import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import ExcludeFoldersFilter from './components/ExcludeFoldersFilter.vue'
import FolderDrilldownFilter from './components/FolderDrilldownFilter.vue'
import { loadState } from '@nextcloud/initial-state'
import IconFileQuestionOutline from 'vue-material-design-icons/FileQuestionOutline.vue'
import IconCalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import IconFolderCancelOutline from 'vue-material-design-icons/FolderCancelOutline.vue'
import IconFolderSearchOutline from 'vue-material-design-icons/FolderSearchOutline.vue'
import NcAppNavigationSpacer from '@nextcloud/vue/components/NcAppNavigationSpacer'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'


export default {
    name: 'App',
    data() {
        return {

            /**
             * the collection of criteria to determine the search
             */
            search_criteria: {
                content: '',
                filename: '',
                file_types: [],
                after_date: null,
                before_date: null,
                start_folder: null,
                exclude_folders: [],
            },

            /**
             * pagination settings
             */
            search_pagination: {
                page: 0,
                size: 10,
            },

            /**
             * sorting settings
             */
            search_sort: 'score',
            search_sort_order: 'desc',

            /**
             * the structure to hold the result coming from the search backend
             */
            search_result: {
                hits: null,
                page: 0,
                size: 10,
                files: []
            },

            /**
             * state management: the constants to be used
             */
            contentStates: {
                INITIAL: 'initial',
                NO_RESULTS: 'no_results',
                SHOW_RESULTS: 'show_results',
                LOADING: 'loading',
            },
            contentState: 'initial', // Default state

            /**
             * determine if a content column makes sense (only if the search backend provides
             * content highlighting such as the Elasticsearch backend)
             */
            show_content_column: false,

            /**
             * the backend provides data for the initial state with the following keys:
             *   - fulltextsearch_available
             */
            initial_state: loadState('filefinder', 'initial_state'),
        }
    },
    components: {
        NcContent,
        NcAppContent,
        NcAppNavigation,
        NcAppNavigationNew,
        NcAppNavigationCaption,
        NcAppNavigationItem,
        NcAppNavigationSpacer,
        NcCounterBubble,
        NcLoadingIcon,
        IconFileQuestionOutline,
        IconCalendarMonthOutline,
        IconFolderCancelOutline,
        IconFolderSearchOutline,
        SearchInput,
        SearchFilelist,
        SearchPagination,
        FileTypeFilter,
        DateFilter,
        ExcludeFoldersFilter,
        FolderDrilldownFilter,
    },
    methods: {

        /**
         * handler when the content search input has been updated
         * @param e the content of the content input element
         */
        onContentUpdate(e) {
            this.search_criteria.content = e;
        },

        /**
         * handler for changes in the filename input element
         * @param e content of the filename input element
         */
        onFilenameUpdate(e) {
            this.search_criteria.filename = e;
        },

        /**
         * handler for changes in the list of selected file types
         * @param e the list of selected file types
         */
        onFileTypeSelect(e) {
            this.search_criteria.file_types = e;
            if (this.contentState != this.contentStates.INITIAL) {
                this.performSearch();
            }
        },

        /**
         * handler for the date filter selector for files modified after the selected date
         * @param date 
         */
        onAfterDateSelect(date) {
            if ((this.search_criteria.before_date !== null) && (date >= this.search_criteria.before_date)) {
                showError(t('filefinder','Selected date must be earlier than the selected second date'));
                this.search_criteria.after_date = null;
            } else {
                this.search_criteria.after_date = date;
                if (this.contentState != this.contentStates.INITIAL) {
                    this.performSearch();
                }
            }
        },

        /**
         * handler for the date filter selector for files modified before the selected date
         * @param date 
         */
        onBeforeDateSelect(date) {
            if ((this.search_criteria.after_date !== null) && (date <= this.search_criteria.after_date)) {
                showError(t('filefinder','Selected date must be later than the selected first date'));
                this.search_criteria.before_date = null;
            } else {
                this.search_criteria.before_date = date;
                if (this.contentState != this.contentStates.INITIAL) {
                    this.performSearch();
                }
            }
       },

       /**
        * handler when the user selected a folder to be added to the list of excluded folders
        * @param newpath 
        */
        addExcludedFolder(newpath) {
            // check if the new path is more specific than an existing one
            if (this.search_criteria.exclude_folders.filter((e) => newpath.startsWith(e)).length > 0) {
                showInfo(t('filefinder','Path is already excluded by other excluded folders'));
            } else {
                // remove already existing paths that are more specific (subfolders) of new path
                var cleaned_folders = this.search_criteria.exclude_folders.filter((el) => !el.startsWith(newpath));

                cleaned_folders.push(newpath);
                this.search_criteria.exclude_folders = cleaned_folders;
                showSuccess(t('filefinder','Path added to excluded folders'));
                if (this.contentState != this.contentStates.INITIAL) {
                    this.performSearch();
                }
            }
        },

        /**
         * handler when the user clicks on the x of an excluded folder
         * @param folders the updated list of folders
         */
        removeExcludeFolder(folders) {
            this.search_criteria.exclude_folders = folders;
            if (this.contentState != this.contentStates.INITIAL) {
                this.performSearch();
            }
        },

        /**
         * handler for setting the start folder
         * @param path 
         */
        setStartFolder(path) {
            this.search_criteria.start_folder = path;
            showSuccess(t('filefinder','Set path as start folder'));
            if (this.contentState != this.contentStates.INITIAL) {
                this.performSearch();
            }
        },

        /**
         * handler for when user removes the start folder from the filter component
         */
        removeStartFolder(e) {
            this.search_criteria.start_folder = null;
            if (this.contentState != this.contentStates.INITIAL) {
                this.performSearch();
            }
        },

        /**
         * handler for changing the search result page
         * @param e 
         */
        onPageUpdate(e) {
            this.search_pagination.page = e;
            this.performSearch();
        },

        /**
         * handler for changing the search result page size
         * @param e 
         */
        onSizeUpdate(e) {
            // compute the new page number so that the same search results remain on the screen
            this.search_pagination.page = Math.floor(this.search_pagination.page * this.search_pagination.size / e);
            this.search_pagination.size = e;
            this.performSearch();
        },

        /**
         * handler for changing the sort field
         * @param field the search field
         * @values path, modified, score, 
         */
        onSortUpdate(field) {
            this.search_sort = field;
            this.search_pagination.page = 0;
            this.performSearch();
        },

        /**
         * handler for changing the sort order
         * @param order the search order
         * @values asc, desc
         */
        onSortOrderUpdate(order) {
            this.search_sort_order = order;
            this.search_pagination.page = 0;
            this.performSearch();
        },

        /**
         * compute the number of active date filters (for showing the counter bubble)
         */
        getNoOfDateFilters() {
            let i = 0;
            if (this.search_criteria.before_date !== null) {
                i++;
            }
            if (this.search_criteria.after_date !== null) {
                i++;
            }
            return i;
        },

        /**
         * handler for the submit button
         */
        onSubmit() {
            this.performSearch();
        },

        /**
         * send a search request to the backend and update the result / state of the app
         */
        performSearch() {
            this.contentState = this.contentStates.LOADING;
            const url = generateUrl('/apps/filefinder/search');

            // when there is no search content, switch sorting to the file path
            if ((this.search_criteria.content === '') && (this.search_sort === 'score')){
                this.search_sort = 'path';
                this.search_sort_order = 'asc';
            }

            // construct the URL parameters for submitting the search
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
                    let stdMsg = t('filefinder','An error has occurred performing search');
                    let errorDetails = error?.response?.data?.error_message ?? '';
                    showError(errorDetails !== '' ? errorDetails : stdMsg);
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

.loading-indicator {
    margin-top: 50px;
}
</style>
