<template>
	<NcAppContent>
		<div id="searchcriteria">
			<h3>File Finder</h3>
			<p>Find the file you are looking for using fine-grained filters</p>
			<SearchInput :modelValue="search_criteria.content" @update="onContentUpdate" label="Content of the file"/>
			<SearchInput :modelValue="search_criteria.filename" @update="onFilenameUpdate" label="Filename. This may contain wildcards" />
			<NcButton
				aria-label="Start Search"
				text="Start Search"
				variant="primary"
				@click="onSubmit" >
				Start Search
			</NcButton>
		</div>
		<div id="searchresult">
			<SearchFilelist :searchresult="search_result" />
		</div>
		<div id="pagination">
			<SearchPagination :searchresult="search_result" @update:page="onPageUpdate" @update:size="onSizeUpdate"/>
		</div>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/components/NcButton'
import SearchInput from './components/SearchInput.vue'
import SearchFilename from './components/SearchFilename.vue'
import SearchFilelist from './components/SearchFilelist.vue'
import SearchPagination from './components/SearchPagination.vue'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
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
            }
        }
    },
    components: {
        NcAppContent,
        NcButton,
        SearchInput,
        SearchFilename,
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
                    this.search_result.files = [];
                    for (const file of response.data.files) {
                        let result = { content_type: file.content_type, name: file.name, highlights: file.highlights, link: file.link, icon_link: file.icon_link };
                        this.search_result.files.push(result);
                    }
                    console.log(this.search_result);
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
	align-items: center;
	margin: 16px;
}
#searchsummary {
	display: flex;
	flex-direction: column;
	align-items: start;
	padding: 32px;
}
#searchresult {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 32px;
}
</style>
