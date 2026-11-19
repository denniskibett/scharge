Hello {{ $firstName ?: 'there' }},

Thank you for asking to connect scharge to your accounting or CRM, and sorry this took longer than the one working day we promised. Here is your sandbox key. It reads a sample property company (3 properties, 34 units, tenants, three months of bills, M-Pesa payments and meter readings), through the same API and fields as a live account.

Sandbox key (shown only in this email, keep it safe):
{{ $key }}

Try it:
curl -H "Authorization: Bearer {{ $key }}" {{ url('/api/v1') }}/invoices?limit=5

The attached PDF ({{ $ref }}) covers the rest:
- The endpoints for invoices, payments, units, tenants and readings, and how to sync them to your books.
- White label: scharge under your name, logo, colours, domain and email sender, live in about 4 weeks.
- Setup fee of KES 50,000, with three ways to pay, one of them nothing upfront if you bring 100 units in 3 months.
- Your revenue share: 20% to 30% of what your clients pay us, every month they stay.

Developer guide: {{ url('/developers') }}

Reply to this email with the system you are connecting and a good time to talk, and we will set up a call{{ $manager?->phone ? ', or call '.$manager->phone : '' }}.

{{ $manager?->name ?? 'The scharge team' }}
scharge · Sharet Africa
