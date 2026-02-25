import { Translator } from "translator";

type GusResponse = {
    tin: string;
    name: string;
    regon: string | null;
    province: string | null;
    street: string | null;
    zipCode: string | null;
    city: string | null;
};

const translator = new Translator();

const normalizeTin = (raw: string): string => (raw || "").replace(/\D/g, "");

const clearStatus = (el: HTMLElement): void => {
    el.textContent = "";
    el.classList.add("d-none");
    el.classList.remove("is-error", "is-success");
};

const showStatus = (
    el: HTMLElement,
    message: string,
    kind: "info" | "error" | "success" = "info"
): void => {
    el.textContent = message;
    el.classList.remove("d-none", "is-error", "is-success");
    if (kind === "error") el.classList.add("is-error");
    if (kind === "success") el.classList.add("is-success");
};

const cssEscape = (value: string): string => {
    const css = (window as any).CSS;
    if (css?.escape) return css.escape(value);
    return value.replace(/"/g, '\\"');
};

const setFieldValueByName = (
    fieldName: string,
    value: string | null | undefined
): void => {
    if (value == null) return;
    const safeName = cssEscape(fieldName);
    const el = document.querySelector<HTMLInputElement | HTMLTextAreaElement>(
        `[name="${safeName}"]`
    );
    if (el) el.value = value;
};

const postJson = async (url: string, body: unknown): Promise<Response> =>
    fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(body),
        credentials: "same-origin",
    });

const readErrorMessage = async (res: Response): Promise<string> => {
    const contentType = res.headers.get("content-type") || "";

    // jeśli backend zwraca JSON {message: "..."} – bierzemy
    if (contentType.includes("application/json")) {
        const data: unknown = await res.json().catch(() => null);
        if (data && typeof data === "object" && "message" in data) {
            const msg = (data as { message?: unknown }).message;
            if (typeof msg === "string" && msg.trim().length > 0) return msg;
        }
    }

    // fallback po statusie
    if (res.status === 404) return await translator.trans("exception.companyNotFoundByTin");
    if (res.status === 429) return await translator.trans("exception.tooManyRequests");

    const tpl = await translator.trans("error.httpStatus"); // np. "Błąd (%status%)."
    return tpl.replace("%status%", String(res.status));
};

const clearGusFields = (): void => {
    const fields = [
        "Company[name]",
        "Company[province]",
        "Company[street]",
        "Company[zipCode]",
        "Company[city]",
    ];

    fields.forEach((field) => setFieldValueByName(field, ""));
};

const wireOne = (container: HTMLElement): void => {
    const url = container.dataset.gusUrl;
    if (!url) return;

    const fetchButton = container.querySelector<HTMLButtonElement>("[data-gus-button]");
    const clearButton = container.querySelector<HTMLButtonElement>("[data-gus-clear]");
    const spinner = container.querySelector<HTMLElement>("[data-gus-spinner]");
    const input = container.querySelector<HTMLInputElement>("input");
    const status = container.querySelector<HTMLElement>("[data-gus-status]");

    if (!fetchButton || !input || !status) return;

    let isLoading = false;

    const updateButtonsState = (): void => {
        const tin = normalizeTin(input.value);
        const canFetch = tin.length === 10;

        fetchButton.disabled = isLoading || !canFetch;
        if (clearButton) clearButton.disabled = isLoading; // czyszczenie dozwolone niezależnie od NIP
    };

    const setLoading = (loading: boolean): void => {
        isLoading = loading;
        spinner?.classList.toggle("d-none", !loading);
        updateButtonsState();
    };

    const runFetch = async (): Promise<void> => {
        clearStatus(status);

        const tin = normalizeTin(input.value);
        if (tin.length !== 10) {
            showStatus(status, await translator.trans("validator.invalidTin"), "error");
            return;
        }

        setLoading(true);
        showStatus(status, await translator.trans("gusApi.fetchingData.loading"), "info");

        try {
            const res = await postJson(url, { tin });

            if (!res.ok) {
                const msg = await readErrorMessage(res);
                showStatus(status, msg, "error");
                return;
            }

            const data = (await res.json()) as GusResponse;

            setFieldValueByName("Company[name]", data.name);
            setFieldValueByName("Company[province]", data.province);
            setFieldValueByName("Company[street]", data.street);
            setFieldValueByName("Company[zipCode]", data.zipCode);
            setFieldValueByName("Company[city]", data.city);

            showStatus(status, await translator.trans("gusApi.fetchingData.success"), "success");
        } catch (e) {
            console.error(e);
            showStatus(status, await translator.trans("gusApi.fetchingData.failed"), "error");
        } finally {
            setLoading(false);
        }
    };

    const runClear = async (): Promise<void> => {
        const confirmMsg = await translator.trans("gusApi.clear.confirm");
        if (!window.confirm(confirmMsg)) return;

        clearGusFields();
        showStatus(status, await translator.trans("gusApi.clear.success"), "success");
        updateButtonsState();
    };

    fetchButton.addEventListener("click", () => void runFetch());

    if (clearButton) {
        clearButton.addEventListener("click", () => void runClear());
    }

    input.addEventListener("keydown", (ev) => {
        if (ev.key === "Enter") {
            ev.preventDefault();
            void runFetch();
        }
    });

    input.addEventListener("input", updateButtonsState);
    updateButtonsState();
};

export const initGusCompanyForm = async (): Promise<void> => {
    // wymuś odświeżenie cache tłumaczeń (przydatne w dev)
    await translator.fetchTranslations(true);

    document.querySelectorAll<HTMLElement>("[data-gus-form]").forEach(wireOne);
};

void initGusCompanyForm();