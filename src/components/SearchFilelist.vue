<template>
    <div class="search-filelist" >
        <table v-if="searchresult.files.length > 0" class="nc-table">
            <thead><tr><th>File</th><th v-if="show_content">Content</th></tr></thead>
            <tbody>
                <tr v-for="file in searchresult.files">
                    <td>
                        <span class="file-link">
                            <img :src="file.icon_link" class="file-icon" />
                            <a :href="file.link" target="_blank">{{ file.name }}</a>
                        </span>
                    </td>
                    <td v-if="show_content"><ul><li v-for="highlight in file.highlights.content"><span class="highlight" v-html="highlight"></span></li></ul></td>
                </tr>
            </tbody>
        </table>
        <div v-else class="noresult">No files to be displayed</div>
    </div>
</template>

<script>
import { mdiFilePdfBox } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

export default {
    name: 'SearchFilelist',
    props: {
        searchresult: {},
        show_content: {
            type: Boolean,
            default: false
        }
    },
    setup() {
        return {
            mdiFilePdfBox,
        }
    },

	components: {
        NcIconSvgWrapper,
        },
	methods: {
	}
}
</script>

<style scoped>
.search-filelist {
    width: 100%;
    display: flex;
    justify-content: center;
    padding: 4px 32px;
    overflow-x: auto;
}

.nc-table th {
    padding: 8px 12px;
    font-weight: bold;
}

.nc-table td {
  padding: 8px 12px;
  vertical-align: top;
}

.nc-table td a {
    text-decoration-line: underline;
}

.nc-table td ul {
    list-style-type: disc;
    padding-left: 20px;
}

.file-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.noresult {
    font-style: italic;
}

::v-deep(span.highlight em) {
    font-style: italic;
    font-weight: 700;
}
</style>