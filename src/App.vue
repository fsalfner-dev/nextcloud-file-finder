<template>
    		<NcContent app-name="filefinder">
			<NcAppNavigation>
				<template #list>
                    <NcAppNavigationCaption name="File Search" isHeading />                                        
                    <SearchInput :modelValue="search_criteria.content" @update="onContentUpdate" label="Content of the file" />
                    <SearchInput :modelValue="search_criteria.filename" @update="onFilenameUpdate" label="Filename (wildcards allowed)" />
					<NcAppNavigationNew text="Search Files" @click="onSubmit"/>
				</template>
			</NcAppNavigation>
			<NcAppContent pageHeading="Search Results">
                <div id="maincontent">
                    <div id="searchresult">
                        <h3 v-if="search_result.hits !== null">Search Results</h3>
                        <p v-if="search_result.hits === 0">No results found.</p>
                        <SearchFilelist v-if="search_result.hits > 0" :searchresult="search_result" :show_content="show_content" />
                    </div>
                    <div id="pagination" v-if="search_result.hits > 0">
                        <SearchPagination :searchresult="search_result" @update:page="onPageUpdate" @update:size="onSizeUpdate" />
                    </div>
                </div>
			</NcAppContent>
		</NcContent>

</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/components/NcButton'
import SearchInput from './components/SearchInput.vue'
import SearchFilelist from './components/SearchFilelist.vue'
import SearchPagination from './components/SearchPagination.vue'
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
            },
            search_pagination: {
                page: 0,
                size: 10,
            },
            search_result: {
                hits: null,
                page: 0,
                size: 10,
                files: []
            },
            show_content: false,
        }
    },
    components: {
        NcContent,
        NcAppContent,
        NcAppNavigation,
        NcAppNavigationItem,
        NcAppNavigationNew,
        NcAppNavigationCaption,
        NcButton,
        SearchInput,
        SearchFilelist,
        SearchPagination,
    },
    methods: {
        onContentUpdate(e) {
            this.search_criteria.content = e;
        },

        onFilenameUpdate(e) {
            this.search_criteria.filename = e;
        },

        onPageUpdate(e) {
            this.search_pagination.page = e;
            this.performSearch();
        },

        onSizeUpdate(e) {
            this.search_pagination.size = e;
            this.performSearch();
        },

        onSubmit() {
            this.performSearch();
            this.show_content = this.search_criteria.content !== '';
        },

        performSearch() {
            const url = generateUrl('/apps/filefinder/search');
            const params = {
                content: this.search_criteria.content,
                filename: this.search_criteria.filename,
                size: this.search_pagination.size,
                page: this.search_pagination.page,
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
                    }));
                })
                .catch((error) => {
                    showError('Search request failed: ' + error.response.data.error_message);
                    console.error(error);
                });
        }
    }
}
</script>

<style scoped lang="scss">
#searchcriteria {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
}

#searchresult {
    padding: 16px;
}

#pagination {
    padding: 16px;
    display: flex;
    justify-content: center;
}
</style>
