import { serverAudits } from "npm:graphql-http";

for (
  const audit of serverAudits({
    url: "http://localhost:8000/graphql",
    fetchFn: fetch,
  })
) {
  Deno.test(
    audit.name,
    // TODO remove when https://github.com/graphql/graphql-http/pull/63#discussion_r1143599460 gets fixed
    { sanitizeResources: false },
    async () => {
      const result = await audit.fn();
      if (result.status === "error") {
        throw result.reason;
      }
      if (result.status === "warn") {
        console.warn(result.reason); // or throw if you want full compliance (warnings are not requirements)
      }
      // result.status === 'ok'
    },
  );
}
