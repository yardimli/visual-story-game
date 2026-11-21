export function storyCover(options) {
    return {
        selected: options.selected,
        images: [],
        categories: {},
        category: '',
        loading: false,
        error: '',
        async open() {
            this.$refs.coverDialog.showModal();
            this.loading = true;
            this.error = '';
            try {
                const response = await fetch(options.url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Could not load project images. Please try again.');
                const data = await response.json();
                this.images = data.assets;
                this.categories = data.image_categories || {};
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        choose(image) {
            this.selected = image;
            this.$refs.coverInput.value = image?.id || '';
            this.$refs.coverInput.dispatchEvent(new Event('change', { bubbles: true }));
            this.$refs.coverDialog.close();
        },
    };
}
