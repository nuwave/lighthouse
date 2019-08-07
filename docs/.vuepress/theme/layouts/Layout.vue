<template>
  <ParentLayout>
    <Footer slot="page-bottom" />
  </ParentLayout>
</template>

<script>
import ParentLayout from "@parent-theme/layouts/Layout.vue";
import Footer from "../components/Footer.vue";

export default {
  components: {
    ParentLayout,
    Footer
  }
};
</script>

<style src="../assets/tailwind.min.css"></style>
