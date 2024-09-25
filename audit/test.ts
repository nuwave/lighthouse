import { serverAudits } from "npm:graphql-http";

for (
  const audit of serverAudits({
    url: "http://server:8000/graphql",
  })
) {
  // if (audit.name !== 'MUST accept application/json and match the content-type') continue;
  Deno.test(
    audit.name,
    // { sanitizeResources: false },
    async () => {
      const result = await audit.fn();
      // Clean up dangling resources
      console.log(result);
      // if ("response" in result) {
      //   await result.response.body?.cancel();
      // }
      if (result.status === "error") {
        throw result.reason;
      }
      if (result.status === "warn") {
        console.warn(result.reason); // or throw if you want full compliance (warnings are not requirements)
      }
    },
  );
}
