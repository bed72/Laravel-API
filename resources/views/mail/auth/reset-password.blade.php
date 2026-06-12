<x-mail::message>
# Olá!

Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para a sua conta.

<x-mail::button :url="$resetUrl">
Redefinir Senha
</x-mail::button>

Este link de redefinição de senha expirará em 60 minutos.

Se você não solicitou uma redefinição de senha, nenhuma ação é necessária.

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
