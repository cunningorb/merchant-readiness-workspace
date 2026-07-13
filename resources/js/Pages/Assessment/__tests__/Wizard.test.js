import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import Wizard from '../Wizard.vue';

// The wizard talks to the assessment + import HTTP surface through axios. We
// mock the module so no real requests fire and each test can assert the exact
// call sequence. This is the wizard's first component test file, so keep this
// axios pattern clean — future wizard tests should follow it.
vi.mock('axios', () => ({
    default: {
        post: vi.fn(),
        get: vi.fn(),
    },
}));

const catalog = [
    {
        key: 'business',
        label: 'Business',
        questions: [{ key: 'company_name', label: 'Company name', type: 'text', required: false }],
    },
    {
        key: 'catalog',
        label: 'Catalog',
        questions: [{ key: 'sku_count', label: 'SKU count', type: 'text', required: false }],
    },
];

let wrapper;

function mountWizard() {
    wrapper = mount(Wizard, {
        props: { catalog },
        global: { stubs: { AssessmentResults: true } },
        attachTo: document.body,
    });

    return wrapper;
}

// Default: every endpoint resolves successfully, CSV import created in the
// `created` (pre-process) state, demo import synchronously `completed`.
function stubHappyPath() {
    axios.post.mockImplementation((url, body) => {
        if (url === '/api/assessments') {
            return Promise.resolve({ data: { assessment: { id: 42 } } });
        }
        if (url.endsWith('/answers')) {
            return Promise.resolve({ data: {} });
        }
        if (url.endsWith('/imports')) {
            const status = body?.provider === 'demo' ? 'completed' : 'created';
            return Promise.resolve({ data: { data_import: { id: 7, status } } });
        }
        if (url.endsWith('/files')) {
            return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
        }
        if (url.endsWith('/process')) {
            return Promise.resolve({ data: { data_import: { id: 7, status: 'queued' } } });
        }
        if (url.endsWith('/cancel')) {
            return Promise.resolve({ data: { data_import: { id: 7, status: 'cancelled' } } });
        }
        if (url.endsWith('/submit')) {
            return Promise.resolve({ data: { report: { url: '/reports/tok' } } });
        }
        return Promise.resolve({ data: {} });
    });

    axios.get.mockResolvedValue({
        data: { data_import: { id: 7, status: 'completed', warnings_count: 0, errors_count: 0 } },
    });
}

function postCallsEndingWith(suffix) {
    return axios.post.mock.calls.filter(([url]) => url.endsWith(suffix));
}

// Navigate from the first section to the import step.
async function reachImportStep() {
    await wrapper.get('form').trigger('submit'); // Next -> last section
    await wrapper.get('[data-testid="continue-to-import"]').trigger('click');
    await flushPromises();
}

async function selectFile(testid, file) {
    const input = wrapper.get(`[data-testid="${testid}"]`);
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
    await input.trigger('change');
    await flushPromises();
}

function selectFileWithoutWaiting(testid, file) {
    const input = wrapper.get(`[data-testid="${testid}"]`);
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });

    return input.trigger('change');
}

const csvFile = () => new File(['handle,title\nabc,Tee'], 'products.csv', { type: 'text/csv' });

beforeEach(() => {
    axios.post.mockReset();
    axios.get.mockReset();
    stubHappyPath();
});

afterEach(() => {
    wrapper?.unmount();
    vi.useRealTimers();
});

describe('Wizard import step — reaching it', () => {
    it('shows the import step (not immediate submission) after Continue on the last section', async () => {
        mountWizard();
        await reachImportStep();

        expect(wrapper.text()).toContain('Make the estimate sharper with data you already have');
        expect(wrapper.text()).toContain('Exit assessment');
        expect(wrapper.text()).toContain('Back to home');
        expect(wrapper.text()).toContain('Privacy Policy');
        expect(wrapper.text()).toContain('Terms');
        expect(wrapper.find('a[href="/"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="choose-csv"]').exists()).toBe(true);
        expect(postCallsEndingWith('/submit')).toHaveLength(0);
        expect(postCallsEndingWith('/imports')).toHaveLength(0);
    });
});

describe('Wizard import step — Connect Shopify', () => {
    it('renders Connect Shopify as disabled and non-interactive, creating nothing', async () => {
        mountWizard();
        await reachImportStep();

        const button = wrapper.get('[data-testid="connect-shopify"]');
        expect(button.attributes('disabled')).toBeDefined();

        await button.trigger('click');
        await flushPromises();

        expect(postCallsEndingWith('/imports')).toHaveLength(0);
    });
});

describe('Wizard import step — CSV path', () => {
    it('creates the import then attaches the file on first file selection', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        await selectFile('csv-input-catalog', csvFile());

        expect(axios.post).toHaveBeenCalledWith('/api/assessments/42/imports', { provider: 'csv' });

        const [, form] = postCallsEndingWith('/imports/7/files')[0];
        expect(form).toBeInstanceOf(FormData);
        expect(form.get('data_type')).toBe('catalog');
        expect(form.get('file')).toBeInstanceOf(File);

        expect(wrapper.get('[data-testid="csv-state-catalog"]').text()).toContain('Attached');
    });

    it('does not create a second import when a second file is attached', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        await selectFile('csv-input-catalog', csvFile());
        await selectFile('csv-input-orders_returns', csvFile());

        expect(postCallsEndingWith('/imports')).toHaveLength(1);
        expect(postCallsEndingWith('/files')).toHaveLength(2);
    });

    it('shares one in-flight csv import creation across simultaneous file selections', async () => {
        let resolveCreateImport;
        const createImportPromise = new Promise((resolve) => {
            resolveCreateImport = resolve;
        });

        axios.post.mockImplementation((url) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/imports')) {
                return createImportPromise;
            }
            if (url.endsWith('/files')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }

            return Promise.resolve({ data: {} });
        });

        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        const first = selectFileWithoutWaiting('csv-input-catalog', csvFile());
        const second = selectFileWithoutWaiting('csv-input-orders_returns', csvFile());
        await flushPromises();

        expect(postCallsEndingWith('/imports')).toHaveLength(1);

        resolveCreateImport({ data: { data_import: { id: 7, status: 'created' } } });
        await first;
        await second;
        await flushPromises();

        expect(postCallsEndingWith('/imports')).toHaveLength(1);
        expect(postCallsEndingWith('/files')).toHaveLength(2);
    });

    it('keeps Process import disabled while any selected file is still uploading', async () => {
        let resolveUpload;
        const uploadPromise = new Promise((resolve) => {
            resolveUpload = resolve;
        });

        axios.post.mockImplementation((url) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/imports')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/files')) {
                return uploadPromise;
            }

            return Promise.resolve({ data: {} });
        });

        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        const upload = selectFileWithoutWaiting('csv-input-catalog', csvFile());
        await flushPromises();

        expect(wrapper.get('[data-testid="process-import"]').attributes('disabled')).toBeDefined();

        resolveUpload({ data: { data_import: { id: 7, status: 'created' } } });
        await upload;
        await flushPromises();

        expect(wrapper.get('[data-testid="process-import"]').attributes('disabled')).toBeUndefined();
    });

    it('surfaces the server validation message when a file upload fails', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        axios.post.mockImplementation((url) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/imports')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/files')) {
                return Promise.reject({
                    response: { data: { errors: { file: ['The file must be a file of type: csv, txt.'] } } },
                });
            }
            return Promise.resolve({ data: {} });
        });

        await selectFile('csv-input-catalog', csvFile());

        const state = wrapper.get('[data-testid="csv-state-catalog"]');
        expect(state.text()).toContain('The file must be a file of type: csv, txt.');
    });

    it('polls after processing until a terminal status, then stops', async () => {
        vi.useFakeTimers();
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');
        await selectFile('csv-input-catalog', csvFile());

        // process leaves the import in-flight; polling drives it to completion.
        axios.post.mockImplementation((url) => {
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'importing' } } });
            }
            return Promise.resolve({ data: {} });
        });

        let getCount = 0;
        axios.get.mockImplementation(() => {
            getCount += 1;
            const status = getCount >= 2 ? 'completed' : 'importing';
            return Promise.resolve({ data: { data_import: { id: 7, status, warnings_count: 0, errors_count: 0 } } });
        });

        await wrapper.get('[data-testid="process-import"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="csv-progress"]').exists()).toBe(true);

        await vi.advanceTimersByTimeAsync(1500); // poll #1 -> importing
        expect(axios.get).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1500); // poll #2 -> completed, stop
        expect(axios.get).toHaveBeenCalledTimes(2);
        expect(wrapper.get('[data-testid="csv-result"]').text()).toContain('Your store data is in');

        // No further polls after the terminal status.
        await vi.advanceTimersByTimeAsync(4500);
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('renders the completed_with_warnings outcome with a count derived from errors_count and Continue', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');
        await selectFile('csv-input-catalog', csvFile());

        axios.post.mockImplementation((url) => {
            if (url.endsWith('/process')) {
                // Realistic backend output: warnings_count is never populated
                // (always 0); errors_count reflects the data types that failed.
                return Promise.resolve({
                    data: { data_import: { id: 7, status: 'completed_with_warnings', warnings_count: 0, errors_count: 3 } },
                });
            }
            if (url.endsWith('/submit')) {
                return Promise.resolve({ data: { report: { url: '/reports/tok' } } });
            }
            return Promise.resolve({ data: {} });
        });

        await wrapper.get('[data-testid="process-import"]').trigger('click');
        await flushPromises();

        const result = wrapper.get('[data-testid="csv-result"]');
        expect(result.text()).toContain('3 item');
        expect(wrapper.find('[data-testid="csv-continue"]').exists()).toBe(true);

        await wrapper.get('[data-testid="csv-continue"]').trigger('click');
        await flushPromises();
        expect(postCallsEndingWith('/submit')).toHaveLength(1);
    });

    it('renders the failed outcome with try-again and continue-without recovery actions', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');
        await selectFile('csv-input-catalog', csvFile());

        axios.post.mockImplementation((url) => {
            if (url.endsWith('/process')) {
                return Promise.resolve({
                    data: { data_import: { id: 7, status: 'failed', warnings_count: 0, errors_count: 2 } },
                });
            }
            if (url.endsWith('/submit')) {
                return Promise.resolve({ data: { report: { url: '/reports/tok' } } });
            }
            return Promise.resolve({ data: {} });
        });

        await wrapper.get('[data-testid="process-import"]').trigger('click');
        await flushPromises();

        const result = wrapper.get('[data-testid="csv-result"]');
        expect(result.text()).toContain('2 error');
        expect(wrapper.find('[data-testid="csv-try-again"]').exists()).toBe(true);

        // Continue without this data still submits — imports never block submission.
        await wrapper.get('[data-testid="csv-continue-without"]').trigger('click');
        await flushPromises();
        expect(postCallsEndingWith('/submit')).toHaveLength(1);
    });

    it('resets to the pre-process state when Try again is clicked after a failure', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');
        await selectFile('csv-input-catalog', csvFile());

        axios.post.mockImplementation((url) => {
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'failed', errors_count: 2 } } });
            }
            return Promise.resolve({ data: {} });
        });

        await wrapper.get('[data-testid="process-import"]').trigger('click');
        await flushPromises();
        await wrapper.get('[data-testid="csv-try-again"]').trigger('click');

        expect(wrapper.find('[data-testid="csv-result"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="csv-state-catalog"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="process-import"]').attributes('disabled')).toBeDefined();
    });

    it('cancels an in-flight import, calling the cancel endpoint and returning to pre-process state', async () => {
        vi.useFakeTimers();
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');
        await selectFile('csv-input-catalog', csvFile());

        axios.post.mockImplementation((url) => {
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'importing' } } });
            }
            if (url.endsWith('/cancel')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'cancelled' } } });
            }
            return Promise.resolve({ data: {} });
        });

        await wrapper.get('[data-testid="process-import"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="cancel-import"]').exists()).toBe(true);

        await wrapper.get('[data-testid="cancel-import"]').trigger('click');
        await flushPromises();

        expect(postCallsEndingWith('/cancel')).toHaveLength(1);
        expect(wrapper.find('[data-testid="csv-progress"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="process-import"]').exists()).toBe(true);

        // Cancelling stops polling — no GETs fire afterwards.
        const getsAfterCancel = axios.get.mock.calls.length;
        await vi.advanceTimersByTimeAsync(4500);
        expect(axios.get.mock.calls.length).toBe(getsAfterCancel);
    });
});

describe('Wizard import step — demo path', () => {
    it('creates a demo import with the exact scenario key and offers Continue', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-demo"]').trigger('click');

        // Exact Task 4 scenario keys.
        expect(wrapper.find('[data-testid="demo-apparel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="demo-footwear"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="demo-home_goods"]').exists()).toBe(true);

        await wrapper.get('[data-testid="demo-apparel"]').trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/assessments/42/imports', {
            provider: 'demo',
            scenario: 'apparel',
        });
        expect(wrapper.find('[data-testid="demo-done"]').exists()).toBe(true);

        await wrapper.get('[data-testid="demo-continue"]').trigger('click');
        await flushPromises();
        expect(postCallsEndingWith('/submit')).toHaveLength(1);
    });

    it('waits for a queued demo import to complete before offering Continue', async () => {
        vi.useFakeTimers();
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-demo"]').trigger('click');

        axios.post.mockImplementation((url, body) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/imports') && body?.provider === 'demo') {
                return Promise.resolve({ data: { data_import: { id: 9, status: 'queued' } } });
            }
            return Promise.resolve({ data: {} });
        });
        axios.get.mockResolvedValue({ data: { data_import: { id: 9, status: 'completed' } } });

        await wrapper.get('[data-testid="demo-apparel"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="demo-loading"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="demo-continue"]').exists()).toBe(false);

        await vi.advanceTimersByTimeAsync(1500);

        expect(axios.get).toHaveBeenCalledWith('/api/assessments/42/imports/9');
        expect(wrapper.find('[data-testid="demo-done"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="demo-continue"]').exists()).toBe(true);
    });
});

describe('Wizard import step — skip / continue manually', () => {
    it('submits without creating any import when Continue manually is used', async () => {
        mountWizard();
        await reachImportStep();

        await wrapper.get('[data-testid="continue-manually"]').trigger('click');
        await flushPromises();

        expect(postCallsEndingWith('/submit')).toHaveLength(1);
        expect(postCallsEndingWith('/imports')).toHaveLength(0);
    });

    it('submits without creating any import when Skip for now is used', async () => {
        mountWizard();
        await reachImportStep();

        await wrapper.get('[data-testid="skip-import"]').trigger('click');
        await flushPromises();

        expect(postCallsEndingWith('/submit')).toHaveLength(1);
        expect(postCallsEndingWith('/imports')).toHaveLength(0);
    });
});

describe('Wizard import step — draft preservation', () => {
    it('leaves answers and the current section untouched when an import fails', async () => {
        mountWizard();

        await wrapper.get('input[type="text"]').setValue('Acme Co');
        await reachImportStep();

        // Demo import fails.
        axios.post.mockImplementation((url) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/imports')) {
                return Promise.reject({ response: { data: {} } });
            }
            return Promise.resolve({ data: {} });
        });

        await wrapper.get('[data-testid="choose-demo"]').trigger('click');
        await wrapper.get('[data-testid="demo-apparel"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="demo-error"]').exists()).toBe(true);

        // Draft progress survived: still on the last section, answer intact.
        await wrapper.get('[data-testid="back-to-questions"]').trigger('click');
        expect(wrapper.text()).toContain('Section 2 of 2');

        // Navigate back to the first section and confirm the typed answer remains.
        const businessTab = wrapper.findAll('button').find((button) => button.text() === 'Business');
        await businessTab.trigger('click');
        expect(wrapper.get('input[type="text"]').element.value).toBe('Acme Co');
    });
});

describe('Wizard import step — timer cleanup', () => {
    it('clears the polling interval on unmount so no request fires afterwards', async () => {
        vi.useFakeTimers();
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');
        await selectFile('csv-input-catalog', csvFile());

        axios.post.mockImplementation((url) => {
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'importing' } } });
            }
            return Promise.resolve({ data: {} });
        });
        axios.get.mockResolvedValue({ data: { data_import: { id: 7, status: 'importing' } } });

        await wrapper.get('[data-testid="process-import"]').trigger('click');
        await flushPromises();

        const getsBeforeUnmount = axios.get.mock.calls.length;
        wrapper.unmount();
        wrapper = null;

        await vi.advanceTimersByTimeAsync(6000);
        expect(axios.get.mock.calls.length).toBe(getsBeforeUnmount);
    });
});
