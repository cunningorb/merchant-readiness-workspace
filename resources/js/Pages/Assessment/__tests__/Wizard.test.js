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
        questions: [
            { key: 'business.company_name', label: 'Company name', type: 'text', required: true },
            { key: 'business.contact_email', label: 'Work email', type: 'email', required: true },
            { key: 'business.monthly_order_volume', label: 'Monthly order volume', type: 'select', required: true, options: ['Under 1,000', '1,000-10,000'] },
        ],
    },
    {
        key: 'catalog',
        label: 'Catalog',
        questions: [{ key: 'catalog.sku_count', label: 'SKU count', type: 'text', required: false }],
    },
    {
        key: 'return_policy',
        label: 'Return Policy',
        questions: [{ key: 'return_policy.window_days', label: 'Return window', type: 'select', required: true, options: ['15-30 days'] }],
    },
    {
        key: 'exchanges',
        label: 'Exchanges',
        questions: [{ key: 'exchanges.offered', label: 'Do you offer exchanges?', type: 'boolean', required: true }],
    },
    {
        key: 'manual_operations',
        label: 'Manual Operations',
        questions: [{ key: 'manual_operations.weekly_hours', label: 'Weekly manual returns hours', type: 'text', required: false }],
    },
    {
        key: 'platform',
        label: 'Platform',
        questions: [{ key: 'platform.ecommerce_platform', label: 'Commerce platform', type: 'text', required: false }],
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
            return Promise.resolve({ data: { assessment: { id: 42, resume_url: '/assessment/42' } } });
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
            return Promise.resolve({ data: { data_import: { id: 7, status: 'completed', warnings_count: 0, errors_count: 0 } } });
        }
        if (url.endsWith('/cancel')) {
            return Promise.resolve({ data: { data_import: { id: 7, status: 'cancelled' } } });
        }
        if (url.endsWith('/website-scan')) {
            return Promise.resolve({
                data: {
                    evidence: {},
                    answers: [],
                    merchant: { website: body?.url },
                },
            });
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
    await wrapper.get('form').trigger('submit');
    await wrapper.get('form').trigger('submit');
    await wrapper.get('form').trigger('submit');
    await wrapper.get('[data-testid="continue-to-import"]').trigger('click');
    await flushPromises();
}

async function openManualAnswers() {
    const button = wrapper.findAll('button').find((candidate) => candidate.text().includes('manual answers'));
    if (button.attributes('aria-expanded') !== 'true') {
        await button.trigger('click');
    }
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

describe('Wizard draft resume', () => {
    it('hydrates answers and website evidence from an existing assessment', async () => {
        wrapper = mount(Wizard, {
            props: {
                catalog,
                initialAssessment: {
                    id: 'draft-123',
                    status: 'draft',
                    answers: [
                        { question_key: 'business.company_name', section: 'business', value: 'Resume Co' },
                        { question_key: 'business.contact_email', section: 'business', value: 'ops@resume.test' },
                    ],
                    evidence: {
                        'business.company_name': [
                            { source_label: 'Website scan', value: 'Resume Co', evidence_snippet: 'Resume Co' },
                        ],
                    },
                    merchant: { website: 'https://resume.test' },
                },
            },
            global: { stubs: { AssessmentResults: true } },
            attachTo: document.body,
        });

        expect(wrapper.text()).toContain("Evaluate Resume Co's returns operation.");
        expect(wrapper.get('input[type="url"]').element.value).toBe('https://resume.test');

        await openManualAnswers();
        expect(wrapper.get('input[type="text"]').element.value).toBe('Resume Co');
        expect(wrapper.text()).toContain('Suggested from Website scan: Resume Co');
    });

    it('replaces the browser URL with the resume URL when a new draft is created', async () => {
        const replaceState = vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});

        mountWizard();
        await openManualAnswers();
        await wrapper.get('input[type="text"]').setValue('New Co');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(replaceState).toHaveBeenCalledWith(window.history.state, '', '/assessment/42');
    });
});

describe('Wizard website scan', () => {
    it('opens manual answers when a scan leaves required blanks', async () => {
        axios.post.mockImplementation((url, body) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/website-scan')) {
                return Promise.resolve({
                    data: {
                        evidence: {},
                        answers: [
                            { question_key: 'business.contact_email', section: 'business', value: 'hello@example.test' },
                        ],
                        merchant: { website: body.url },
                    },
                });
            }

            return Promise.resolve({ data: {} });
        });

        mountWizard();
        await wrapper.get('input[type="url"]').setValue('example.test');
        await wrapper.get('[data-testid="scan-website"]').trigger('click');
        await flushPromises();

        const manualToggle = wrapper.findAll('button').find((button) => button.text().includes('manual answers'));
        expect(manualToggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.text()).toContain('Company name');
    });

    it('highlights Next when a scan fills all required answers on the step', async () => {
        axios.post.mockImplementation((url, body) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/website-scan')) {
                return Promise.resolve({
                    data: {
                        evidence: {},
                        answers: [
                            { question_key: 'business.company_name', section: 'business', value: 'Example Co' },
                            { question_key: 'business.contact_email', section: 'business', value: 'hello@example.test' },
                        ],
                        merchant: { website: body.url },
                    },
                });
            }

            return Promise.resolve({ data: {} });
        });

        mountWizard();
        await wrapper.get('input[type="url"]').setValue('example.test');
        await wrapper.get('[data-testid="scan-website"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="next-step"]').classes()).toContain('ring-4');
    });

    it('autosaves only questions rendered on the current assisted step', async () => {
        vi.useFakeTimers();
        mountWizard();

        await openManualAnswers();
        await wrapper.findAll('input').find((input) => input.attributes('type') === 'text').setValue('Example Co');
        await vi.advanceTimersByTimeAsync(600);
        await flushPromises();

        const [, body] = postCallsEndingWith('/answers')[0];
        const questionKeys = body.answers.map((answer) => answer.question_key);

        expect(questionKeys).toContain('business.company_name');
        expect(questionKeys).toContain('platform.ecommerce_platform');
        expect(questionKeys).not.toContain('business.monthly_order_volume');
    });

    it('clears previous website-filled answers when the latest scan omits them', async () => {
        axios.post.mockImplementation((url, body) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/website-scan')) {
                return Promise.resolve({
                    data: {
                        evidence: {},
                        answers: [
                            { question_key: 'business.contact_email', section: 'business', value: 'first@example.test' },
                        ],
                        merchant: { website: body.url },
                    },
                });
            }
            if (url.endsWith('/answers')) {
                return Promise.resolve({ data: {} });
            }

            return Promise.resolve({ data: {} });
        });

        mountWizard();
        await wrapper.get('input[type="url"]').setValue('first.test');
        await wrapper.get('[data-testid="scan-website"]').trigger('click');
        await flushPromises();

        await openManualAnswers();
        const emailInput = wrapper.findAll('input').find((input) => input.attributes('type') === 'email');
        expect(emailInput.element.value).toBe('first@example.test');

        axios.post.mockImplementation((url, body) => {
            if (url.endsWith('/website-scan')) {
                return Promise.resolve({
                    data: {
                        evidence: {},
                        answers: [],
                        merchant: { website: body.url },
                    },
                });
            }
            if (url.endsWith('/answers')) {
                return Promise.resolve({ data: {} });
            }

            return Promise.resolve({ data: { assessment: { id: 42 } } });
        });

        await wrapper.get('input[type="url"]').setValue('second.test');
        await wrapper.get('[data-testid="scan-website"]').trigger('click');
        await flushPromises();

        expect(emailInput.element.value).toBe('');
    });
});

describe('Wizard assisted grouping', () => {
    it('places monthly order volume on the catalog step', async () => {
        mountWizard();
        await openManualAnswers();
        expect(wrapper.text()).not.toContain('Monthly order volume');

        await wrapper.get('form').trigger('submit');
        await openManualAnswers();

        expect(wrapper.text()).toContain('Monthly order volume');
        expect(wrapper.text()).toContain('SKU count');
    });

    it('uses a policy URL scan instead of CSV evidence on the policy step', async () => {
        mountWizard();
        await wrapper.get('form').trigger('submit');
        expect(wrapper.find('[data-testid="csv-input-orders_returns"]').exists()).toBe(true);

        await wrapper.get('form').trigger('submit');

        expect(wrapper.text()).toContain('Return policy URL');
        expect(wrapper.get('[data-testid="scan-website"]').text()).toContain('Scan policy');
        expect(wrapper.find('[data-testid="csv-input-orders_returns"]').exists()).toBe(false);
    });

    it('skips the policy URL prompt when the storefront scan already filled policy answers', async () => {
        axios.post.mockImplementation((url, body) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/website-scan')) {
                return Promise.resolve({
                    data: {
                        evidence: {},
                        answers: [
                            { question_key: 'business.company_name', section: 'business', value: 'Example Co' },
                            { question_key: 'business.contact_email', section: 'business', value: 'hello@example.test' },
                            { question_key: 'return_policy.window_days', section: 'return_policy', value: '15-30 days' },
                            { question_key: 'exchanges.offered', section: 'exchanges', value: true },
                        ],
                        merchant: { website: body.url },
                    },
                });
            }

            return Promise.resolve({ data: {} });
        });

        mountWizard();
        await wrapper.get('input[type="url"]').setValue('example.test');
        await wrapper.get('[data-testid="scan-website"]').trigger('click');
        await flushPromises();

        await wrapper.get('form').trigger('submit');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.text()).not.toContain('Return policy URL');
        expect(wrapper.find('[data-testid="scan-website"]').exists()).toBe(false);
    });

    it('personalizes the wizard title after company name is entered', async () => {
        mountWizard();
        await openManualAnswers();
        await wrapper.findAll('input').find((input) => input.attributes('type') === 'text').setValue('Cotopaxi');

        expect(wrapper.text()).toContain("Evaluate Cotopaxi's returns operation.");
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
    it('creates, attaches, and processes an import on first file selection', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        await selectFile('csv-input-catalog', csvFile());

        expect(axios.post).toHaveBeenCalledWith('/api/assessments/42/imports', { provider: 'csv' });

        const [, form] = postCallsEndingWith('/imports/7/files')[0];
        expect(form).toBeInstanceOf(FormData);
        expect(form.get('data_type')).toBe('catalog');
        expect(form.get('file')).toBeInstanceOf(File);

        expect(postCallsEndingWith('/process')).toHaveLength(1);
        expect(wrapper.get('[data-testid="csv-state-catalog"]').text()).toContain('Processed');
    });

    it('creates a fresh import for each uploaded file', async () => {
        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        await selectFile('csv-input-catalog', csvFile());
        await selectFile('csv-input-orders_returns', csvFile());

        expect(postCallsEndingWith('/imports')).toHaveLength(2);
        expect(postCallsEndingWith('/files')).toHaveLength(2);
        expect(postCallsEndingWith('/process')).toHaveLength(2);
    });

    it('creates independent imports across simultaneous file selections', async () => {
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
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'completed', warnings_count: 0, errors_count: 0 } } });
            }

            return Promise.resolve({ data: {} });
        });

        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        const first = selectFileWithoutWaiting('csv-input-catalog', csvFile());
        const second = selectFileWithoutWaiting('csv-input-orders_returns', csvFile());
        await flushPromises();

        expect(postCallsEndingWith('/imports')).toHaveLength(2);

        resolveCreateImport({ data: { data_import: { id: 7, status: 'created' } } });
        await first;
        await second;
        await flushPromises();

        expect(postCallsEndingWith('/imports')).toHaveLength(2);
        expect(postCallsEndingWith('/files')).toHaveLength(2);
    });

    it('keeps file inputs disabled while any selected file is still uploading', async () => {
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
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'completed', warnings_count: 0, errors_count: 0 } } });
            }

            return Promise.resolve({ data: {} });
        });

        mountWizard();
        await reachImportStep();
        await wrapper.get('[data-testid="choose-csv"]').trigger('click');

        const upload = selectFileWithoutWaiting('csv-input-catalog', csvFile());
        await flushPromises();

        expect(wrapper.get('[data-testid="csv-input-orders_returns"]').attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('Uploading');
        expect(wrapper.text()).toContain('Waiting');

        resolveUpload({ data: { data_import: { id: 7, status: 'created' } } });
        await upload;
        await flushPromises();

        expect(wrapper.get('[data-testid="csv-input-orders_returns"]').attributes('disabled')).toBeUndefined();
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

    it('polls after upload-triggered processing until a terminal status, then stops', async () => {
        vi.useFakeTimers();
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
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
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

        await selectFile('csv-input-catalog', csvFile());
        await flushPromises();
        expect(wrapper.find('[data-testid="csv-progress"]').exists()).toBe(true);

        await vi.advanceTimersByTimeAsync(1500); // poll #1 -> importing
        expect(axios.get).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1500); // poll #2 -> completed, stop
        expect(axios.get).toHaveBeenCalledTimes(2);
        expect(wrapper.get('[data-testid="csv-result"]').text()).toContain('Store data processed');

        // No further polls after the terminal status.
        await vi.advanceTimersByTimeAsync(4500);
        expect(axios.get).toHaveBeenCalledTimes(2);
    });

    it('renders the completed_with_warnings outcome with a count derived from warnings_count and Continue', async () => {
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
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/process')) {
                return Promise.resolve({
                    data: { data_import: { id: 7, status: 'completed_with_warnings', warnings_count: 2, errors_count: 3 } },
                });
            }
            if (url.endsWith('/submit')) {
                return Promise.resolve({ data: { report: { url: '/reports/tok' } } });
            }
            return Promise.resolve({ data: {} });
        });

        await selectFile('csv-input-catalog', csvFile());
        await flushPromises();

        const result = wrapper.get('[data-testid="csv-result"]');
        expect(result.text()).toContain('2 item');
        expect(wrapper.find('[data-testid="csv-continue"]').exists()).toBe(true);

        await wrapper.get('[data-testid="csv-continue"]').trigger('click');
        await flushPromises();
        expect(postCallsEndingWith('/submit')).toHaveLength(1);
    });

    it('applies inferred answers returned by a processed import', async () => {
        mountWizard();
        await wrapper.get('form').trigger('submit');

        axios.post.mockImplementation((url) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/imports')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/files')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/process')) {
                return Promise.resolve({
                    data: {
                        data_import: { id: 7, status: 'completed', warnings_count: 0, errors_count: 0 },
                        answers: [
                            { question_key: 'business.monthly_order_volume', section: 'business', value: '1,000-10,000' },
                        ],
                    },
                });
            }
            return Promise.resolve({ data: {} });
        });

        await selectFile('csv-input-orders_returns', csvFile());
        await flushPromises();
        await openManualAnswers();

        const monthlyOrderSelect = wrapper.findAll('select').find((select) => select.text().includes('Under 1,000'));
        expect(monthlyOrderSelect.element.value).toBe('1,000-10,000');
    });

    it('clears the processed import banner when moving to another wizard step', async () => {
        mountWizard();
        await wrapper.get('form').trigger('submit');

        await selectFile('csv-input-orders_returns', csvFile());
        await flushPromises();
        expect(wrapper.find('[data-testid="csv-result"]').exists()).toBe(true);

        await wrapper.get('form').trigger('submit');

        expect(wrapper.find('[data-testid="csv-result"]').exists()).toBe(false);
    });

    it('submits from the final step instead of showing the import screen after all CSV types were processed inline', async () => {
        mountWizard();

        await wrapper.get('form').trigger('submit');
        await selectFile('csv-input-catalog', csvFile());
        await selectFile('csv-input-orders_returns', csvFile());

        await wrapper.get('form').trigger('submit');
        await wrapper.get('form').trigger('submit');
        await selectFile('csv-input-inventory_locations', csvFile());

        await wrapper.get('[data-testid="continue-to-import"]').trigger('click');
        await flushPromises();

        expect(postCallsEndingWith('/submit')).toHaveLength(1);
        expect(wrapper.text()).not.toContain('Make the estimate sharper with data you already have');
    });

    it('opens manual answers when a processed import leaves required catalog blanks', async () => {
        mountWizard();
        await wrapper.get('form').trigger('submit');

        axios.post.mockImplementation((url) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/imports')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/files')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/process')) {
                return Promise.resolve({
                    data: {
                        data_import: { id: 7, status: 'completed', warnings_count: 0, errors_count: 0 },
                        answers: [],
                    },
                });
            }
            return Promise.resolve({ data: {} });
        });

        await selectFile('csv-input-orders_returns', csvFile());
        await flushPromises();

        const manualToggle = wrapper.findAll('button').find((button) => button.text().includes('manual answers'));
        expect(manualToggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.text()).toContain('Monthly order volume');
    });

    it('renders the failed outcome with try-again and continue-without recovery actions', async () => {
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
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
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

        await selectFile('csv-input-catalog', csvFile());
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

        axios.post.mockImplementation((url) => {
            if (url === '/api/assessments') {
                return Promise.resolve({ data: { assessment: { id: 42 } } });
            }
            if (url.endsWith('/imports')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/files')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'failed', errors_count: 2 } } });
            }
            return Promise.resolve({ data: {} });
        });

        await selectFile('csv-input-catalog', csvFile());
        await flushPromises();
        await wrapper.get('[data-testid="csv-try-again"]').trigger('click');

        expect(wrapper.find('[data-testid="csv-result"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="csv-state-catalog"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="process-import"]').exists()).toBe(false);
    });

    it('cancels an in-flight import, calling the cancel endpoint and returning to pre-process state', async () => {
        vi.useFakeTimers();
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
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'importing' } } });
            }
            if (url.endsWith('/cancel')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'cancelled' } } });
            }
            return Promise.resolve({ data: {} });
        });

        await selectFile('csv-input-catalog', csvFile());
        await flushPromises();
        expect(wrapper.find('[data-testid="cancel-import"]').exists()).toBe(true);

        await wrapper.get('[data-testid="cancel-import"]').trigger('click');
        await flushPromises();

        expect(postCallsEndingWith('/cancel')).toHaveLength(1);
        expect(wrapper.find('[data-testid="csv-progress"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="process-import"]').exists()).toBe(false);

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

        await openManualAnswers();
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
        expect(wrapper.text()).toContain('Step 4 of 4');

        // Navigate back to the first step and confirm the typed answer remains.
        const profileTab = wrapper.findAll('button').find((button) => button.text().includes('Profile'));
        await profileTab.trigger('click');
        await openManualAnswers();
        expect(wrapper.get('input[type="text"]').element.value).toBe('Acme Co');
    });
});

describe('Wizard import step — timer cleanup', () => {
    it('clears the polling interval on unmount so no request fires afterwards', async () => {
        vi.useFakeTimers();
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
                return Promise.resolve({ data: { data_import: { id: 7, status: 'created' } } });
            }
            if (url.endsWith('/process')) {
                return Promise.resolve({ data: { data_import: { id: 7, status: 'importing' } } });
            }
            return Promise.resolve({ data: {} });
        });
        axios.get.mockResolvedValue({ data: { data_import: { id: 7, status: 'importing' } } });

        await selectFile('csv-input-catalog', csvFile());
        await flushPromises();

        const getsBeforeUnmount = axios.get.mock.calls.length;
        wrapper.unmount();
        wrapper = null;

        await vi.advanceTimersByTimeAsync(6000);
        expect(axios.get.mock.calls.length).toBe(getsBeforeUnmount);
    });
});
