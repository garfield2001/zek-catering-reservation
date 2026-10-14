import { request, type FullConfig } from "@playwright/test";

export default async function verifyTestingServer(config: FullConfig) {
    const context = await request.newContext();
    try {
        const response = await context.get(
            `${config.projects[0].use.baseURL}/login`,
        );
        if (
            !response.ok() ||
            response.headers()["x-catering-test-database"] !==
                "zek_catering_test:5432"
        ) {
            throw new Error(
                "Browser tests require the guarded testing server on zek_catering_test:5432.",
            );
        }
    } finally {
        await context.dispose();
    }
}
