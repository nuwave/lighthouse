export default ({
  Vue, // the version of Vue being used in the VuePress app
  options, // the options for the root Vue instance
  router, // the router instance for the app
  siteData, // site metadata
}) => {
  router.beforeEach((to, from, next) => {
    const pathFragments = to.path.split("/");
    const version = pathFragments[1];
    const rest = pathFragments.splice(2).join("/");

    // Used in the `Get Started` link of the index page
    if (version === "latest") {
      return next({ path: `/${siteData.themeConfig.latest}/${rest}` });
    }

    // We previously had docs for each minor version, but now only
    // keep the latest docs of major versions around. In consideration
    // of potential old links floating around, we redirect them.
    const versionParts = version.split(".");
    if (versionParts.length > 1) {
      const major = versionParts[0];
      return next({ path: `/${major}/${rest}` });
    }

    return next();
  });
};
