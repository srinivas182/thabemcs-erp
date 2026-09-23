/*
 * Site teams on phones: pulling their projects and pushing what they captured. This is the traffic that
 * arrives in bursts when a site regains signal, so it ramps hard and holds.
 *
 *   k6 run -e BASE_URL=https://staging.thabekhulu.co.za -e TOKEN=... load-tests/site-app.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const failures = new Rate('failed_requests');
const BASE = __ENV.BASE_URL || 'http://localhost';
const TOKEN = __ENV.TOKEN || '';
const headers = { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json', 'Content-Type': 'application/json' };

export const options = {
    stages: [
        { duration: '1m', target: 200 },
        { duration: '2m', target: 1000 },   // a site comes back into signal
        { duration: '5m', target: 1000 },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<800'],
        failed_requests: ['rate<0.01'],
    },
};

export default function () {
    const projects = http.get(`${BASE}/api/v1/site/projects`, { headers });
    failures.add(projects.status !== 200);
    check(projects, { 'projects listed': (r) => r.status === 200 });

    // Second call should come back as "not modified" thanks to the ETag.
    const etag = projects.headers.ETag;
    if (etag) {
        const again = http.get(`${BASE}/api/v1/site/projects`, { headers: { ...headers, 'If-None-Match': etag } });
        check(again, { 'unchanged list is cheap': (r) => r.status === 304 });
    }

    const diary = http.post(`${BASE}/api/v1/site/diary-entries`, JSON.stringify({
        clientId: `${__VU}-${__ITER}-${Date.now()}`,
        projectId: __ENV.PROJECT_ID,
        entryDate: new Date().toISOString().slice(0, 10),
        workDone: 'Load test entry',
        labourCount: 12,
    }), { headers });
    failures.add(diary.status >= 400);

    sleep(Math.random() * 10 + 5);
}
