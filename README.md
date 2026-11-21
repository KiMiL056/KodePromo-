# KodePromo — Mesin Voucher Diskon REST API & E-Commerce Demo

Repository Tugas Besar 1 Mata Kuliah Komputasi Awan / DevOps (Studi Kasus **K08 — KodePromo**).

## Struktur Repository
- `api/` — Aplikasi utama KodePromo REST API berbasis PHP & Laravel (pusat seluruh logika dan aturan bisnis voucher).
- `ecommerce-demo/` — Aplikasi toko online sederhana sebagai client pemanggil API KodePromo.
- `docs/` — Dokumentasi kontrak API (OpenAPI), Postman Collection, dan diagram arsitektur/pipeline.
- `docker-compose.yml` — Orkestrasi container service (`api`, `db`, `ecommerce-demo`).
- `Jenkinsfile` — Pipeline CI/CD otomatis (Checkout, Test, Build Image, Push Image, Approval, Deploy, Smoke Test).

## Tim Pengembang & Pembagian Peran
- **Tech Lead / Backend:** Mubarak Subkhi Muhidin
- **DevOps Engineer:** (Anggota 2)
- **Container Engineer:** (Anggota 3)
- **QA Engineer & Tech Writer:** (Anggota 4)

## Fitur Utama
1. Manajemen Kampanye Promosi (`CRUD /api/v1/campaigns`) dan Laporan Penggunaan (`/api/v1/campaigns/{id}/report`).
2. Manajemen Kode Voucher (`CRUD /api/v1/vouchers`).
3. Validasi Voucher (`POST /api/v1/vouchers/validate`) dan Redeem Voucher (`POST /api/v1/vouchers/redeem`) dengan aturan bisnis BR-01 s.d. BR-09.
4. Health Check Service & Database (`GET /health`).
5. Aplikasi E-Commerce Demo untuk simulasi keranjang belanja, validasi kode promo, dan checkout.
