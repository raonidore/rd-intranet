<style>
.auth-body {
    min-height: 100vh;
    background: radial-gradient(circle at top left, #1e293b 0%, #0f172a 60%, #0b1120 100%);
    padding: 24px;
}
.auth-card {
    width: 420px;
    max-width: 100%;
    border-radius: 20px;
    box-shadow: 0 24px 60px rgba(0,0,0,.35);
}
.auth-logo {
    max-height: 70px;
    max-width: 100%;
    border-radius: 12px;
}
.auth-input-group .input-group-text {
    background: #f8fafc;
    border-right: 0;
    color: #64748b;
}
.auth-input-group .form-control {
    border-left: 0;
}
.auth-input-group .form-control:focus {
    box-shadow: none;
    border-color: #86b7fe;
}
.auth-input-group:focus-within {
    box-shadow: 0 0 0 .25rem rgba(37,99,235,.15);
    border-radius: .375rem;
}
.auth-input-group:focus-within .input-group-text {
    border-color: #86b7fe;
}
.auth-btn {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border: 0;
    border-radius: 10px;
    padding: 10px;
    font-weight: 600;
    transition: transform .15s ease, box-shadow .15s ease;
}
.auth-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(37,99,235,.35);
}
.auth-link-esqueci {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13.5px;
    color: #475569;
    text-decoration: none;
    padding: 6px 14px;
    border-radius: 20px;
    transition: background .15s ease, color .15s ease;
}
.auth-link-esqueci:hover {
    background: #eff6ff;
    color: #1d4ed8;
}
.auth-voltar {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13.5px;
    color: #475569;
    text-decoration: none;
    padding: 6px 14px;
    border-radius: 20px;
    transition: background .15s ease, color .15s ease;
}
.auth-voltar:hover {
    background: #f1f5f9;
    color: #1e293b;
}
.senha-ui-grupo:focus-within {
    box-shadow: 0 0 0 .25rem rgba(37,99,235,.15);
    border-radius: .375rem;
}
.senha-ui-grupo .btn-outline-secondary {
    border-color: #ced4da;
}
.senha-ui-requisito { display: flex; align-items: center; gap: 6px; font-size: 12.5px; }
</style>
