<?php

namespace Database\Seeders;

use App\Models\OrgaoGoverno;
use App\Models\TipoOrgao;
use Illuminate\Database\Seeder;

class addNewOrgaoSeeder extends Seeder
{
    public function run(): void
    {
        $secretaria = TipoOrgao::where('slug', 'secretaria')->first();
        $autarquia = TipoOrgao::where('slug', 'autarquia')->first();
        $empresaEstadual = TipoOrgao::where('slug', 'empresa_estadual')->first();
        $orgao = TipoOrgao::where('slug', 'orgao')->first();
        $superintendencia = TipoOrgao::where('slug', 'superintendencia')->first();
        $segurancaPublica = TipoOrgao::where('slug', 'seguranca_publica')->first();

        $orgaos = [

            // Secretarias
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Relações Institucionais', 'sigla' => 'SERIN'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria da Administração', 'sigla' => 'SAEB'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria do Planejamento', 'sigla' => 'SEPLAN'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Administração Penitenciária e Ressocialização', 'sigla' => 'SEAB'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Desenvolvimento Rural', 'sigla' => 'SDR'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Desenvolvimento Econômico', 'sigla' => 'SDE'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Ciência, Tecnologia e Inovação', 'sigla' => 'SECTI'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Assistência e Desenvolvimento Social', 'sigla' => 'SEADES'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Políticas para as Mulheres', 'sigla' => 'SPM'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Promoção da Igualdade Racial e dos Povos e Comunidades Tradicionais', 'sigla' => 'SEPROMI'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria Extraordinária do Sistema Viário Oeste – Ponte Salvador-Itaparica', 'sigla' => 'SEPONTE'],

            // Autarquias
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Agência de Defesa Agropecuária da Bahia', 'sigla' => 'ADAB'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Agência Estadual de Regulação de Serviços Públicos de Energia, Transportes e Comunicações da Bahia', 'sigla' => 'AGERBA'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Departamento de Infraestrutura de Transportes da Bahia', 'sigla' => 'DER-BA'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Departamento Estadual de Trânsito da Bahia', 'sigla' => 'DETRAN-BA'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Instituto Baiano de Metrologia e Qualidade', 'sigla' => 'IBAMETRO'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Instituto Anísio Teixeira', 'sigla' => 'IAT'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Instituto do Patrimônio Artístico e Cultural da Bahia', 'sigla' => 'IPAC'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Junta Comercial do Estado da Bahia', 'sigla' => 'JUCEB'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Departamento de Polícia Técnica', 'sigla' => 'DPT'],

            // Empresas Estaduais
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Companhia de Gás da Bahia', 'sigla' => 'BAHIAGÁS'],
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Empresa de Turismo da Bahia', 'sigla' => 'BAHIATURSA'],
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Companhia de Desenvolvimento e Ação Regional', 'sigla' => 'CAR'],
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Agência de Fomento do Estado da Bahia', 'sigla' => 'DESENBAHIA'],
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Empresa Gráfica da Bahia', 'sigla' => 'EGBA'],
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Agência Baiana de Promoção de Investimentos', 'sigla' => 'BAHIAINVESTE'],

            // Segurança Pública
            ['tipo_orgao_id' => $segurancaPublica->id, 'nome' => 'Polícia Civil da Bahia', 'sigla' => 'PC'],
            ['tipo_orgao_id' => $segurancaPublica->id, 'nome' => 'Polícia Militar da Bahia', 'sigla' => 'PM'],
            ['tipo_orgao_id' => $segurancaPublica->id, 'nome' => 'Corpo de Bombeiros Militar da Bahia', 'sigla' => 'CB'],

            // Superintendências
            ['tipo_orgao_id' => $superintendencia->id, 'nome' => 'Superintendência Baiana de Assistência Técnica e Extensão Rural', 'sigla' => 'BAHIATER'],
            ['tipo_orgao_id' => $superintendencia->id, 'nome' => 'Superintendência de Fomento ao Turismo', 'sigla' => 'Sufotur'],
            ['tipo_orgao_id' => $superintendencia->id, 'nome' => 'Superintendência dos Desportos do Estado da Bahia', 'sigla' => 'SUDESB'],
        ];

        foreach ($orgaos as $orgaoItem) {
            OrgaoGoverno::updateOrCreate(
                ['sigla' => $orgaoItem['sigla']],
                $orgaoItem
            );
        }
    }
}
