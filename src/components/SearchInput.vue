<template>
    <div class="search-input">
        <NcTextField :value="modelValue"
            @input="onInput"
            :label="label"
            trailing-button-icon="close"
            :show-trailing-button="modelValue !== ''"
            @trailing-button-click="clearText">
            <template #icon>
                <Magnify :size="20" />
            </template>
        </NcTextField>    
    </div>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'SearchInput',
    props: {
        modelValue: {
            type: String,
            default: ''
        },
        label: {
            type: String,
            default: ''
        },
    },
    emits: ['update'],

	components: {
		NcTextField,
        Magnify,
        Close
    },
	methods: {
		clearText() {
			this.modelValue = '';
            this.$emit('update', '');
		},
        onInput(event) {
            this.$emit('update', event.target.value)
        }
	}
}
</script>

<style scoped>
.search-input {
    width: 100%;
    display: flex;
    justify-content: center;
    padding: 4px 8px;
}

</style>