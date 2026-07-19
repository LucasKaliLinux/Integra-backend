<?php

namespace Database\Seeders;

use App\Models\OrgaoGoverno;
use App\Models\TipoOrgao;
use Illuminate\Database\Seeder;

class OrgaoGovernoSeeder extends Seeder
{
    public function run(): void
    {
        // ========== FEDERAL ==========

        $presidencia = TipoOrgao::where('slug', 'presidencia')->first();
        $ministerio = TipoOrgao::where('slug', 'ministerio')->first();
        $autarquia = TipoOrgao::where('slug', 'autarquia')->first();
        $agencia = TipoOrgao::where('slug', 'agencia')->first();
        $empresaPublica = TipoOrgao::where('slug', 'empresa_publica')->first();
        $fundacaoPublica = TipoOrgao::where('slug', 'fundacao_publica')->first();

        $orgaosFederais = [
            // Presidência
            ['tipo_orgao_id' => $presidencia->id, 'nome' => 'Casa Civil', 'sigla' => 'CC'],
            ['tipo_orgao_id' => $presidencia->id, 'nome' => 'Secretaria de Comunicação Social', 'sigla' => 'SECOM'],
            ['tipo_orgao_id' => $presidencia->id, 'nome' => 'Secretaria-Geral da Presidência', 'sigla' => 'SG-PR'],

            // Ministérios (principais)
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Agricultura e Pecuária', 'sigla' => 'MAPA'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério das Cidades', 'sigla' => 'MCidades'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Saúde', 'sigla' => 'MS'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Educação', 'sigla' => 'MEC'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Economia', 'sigla' => 'ME'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Infraestrutura', 'sigla' => 'MINFRA'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério do Desenvolvimento Regional', 'sigla' => 'MDR'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério do Turismo', 'sigla' => 'MTur'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Cultura', 'sigla' => 'MinC'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério do Esporte', 'sigla' => 'MEsp'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério do Meio Ambiente', 'sigla' => 'MMA'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério de Minas e Energia', 'sigla' => 'MME'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Justiça e Segurança Pública', 'sigla' => 'MJSP'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Defesa', 'sigla' => 'MD'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Ciência, Tecnologia e Inovação', 'sigla' => 'MCTI'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério do Trabalho e Emprego', 'sigla' => 'MTE'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério do Desenvolvimento Social', 'sigla' => 'MDS'],
            ['tipo_orgao_id' => $ministerio->id, 'nome' => 'Ministério da Integração Nacional', 'sigla' => 'MI'],

            // Autarquias
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Instituto Brasileiro do Meio Ambiente e dos Recursos Naturais Renováveis', 'sigla' => 'IBAMA'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Instituto Nacional do Seguro Social', 'sigla' => 'INSS'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Departamento Nacional de Infraestrutura de Transportes', 'sigla' => 'DNIT'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Instituto Nacional de Colonização e Reforma Agrária', 'sigla' => 'INCRA'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Superintendência do Desenvolvimento do Nordeste', 'sigla' => 'SUDENE'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Companhia de Desenvolvimento dos Vales do São Francisco e do Parnaíba', 'sigla' => 'CODEVASF'],
            ['tipo_orgao_id' => $autarquia->id, 'nome' => 'Fundação Nacional dos Povos Indígenas', 'sigla' => 'FUNAI'],

            // Agências
            ['tipo_orgao_id' => $agencia->id, 'nome' => 'Agência Nacional de Telecomunicações', 'sigla' => 'ANATEL'],
            ['tipo_orgao_id' => $agencia->id, 'nome' => 'Agência Nacional de Transportes Terrestres', 'sigla' => 'ANTT'],
            ['tipo_orgao_id' => $agencia->id, 'nome' => 'Agência Nacional de Energia Elétrica', 'sigla' => 'ANEEL'],
            ['tipo_orgao_id' => $agencia->id, 'nome' => 'Agência Nacional de Vigilância Sanitária', 'sigla' => 'ANVISA'],
            ['tipo_orgao_id' => $agencia->id, 'nome' => 'Agência Nacional de Águas', 'sigla' => 'ANA'],
            ['tipo_orgao_id' => $agencia->id, 'nome' => 'Agência Nacional do Petróleo', 'sigla' => 'ANP'],

            // Empresas Públicas
            ['tipo_orgao_id' => $empresaPublica->id, 'nome' => 'Caixa Econômica Federal', 'sigla' => 'CEF'],
            ['tipo_orgao_id' => $empresaPublica->id, 'nome' => 'Banco do Brasil', 'sigla' => 'BB'],
            ['tipo_orgao_id' => $empresaPublica->id, 'nome' => 'Petróleo Brasileiro S.A.', 'sigla' => 'Petrobras'],
            ['tipo_orgao_id' => $empresaPublica->id, 'nome' => 'Empresa Brasileira de Correios e Telégrafos', 'sigla' => 'Correios'],

            // Fundações
            ['tipo_orgao_id' => $fundacaoPublica->id, 'nome' => 'Fundação Nacional de Saúde', 'sigla' => 'FUNASA'],
            ['tipo_orgao_id' => $fundacaoPublica->id, 'nome' => 'Fundação Biblioteca Nacional', 'sigla' => 'FBN'],
        ];

        // ========== ESTADUAL (BAHIA) ==========

        $governadoria = TipoOrgao::where('slug', 'governadoria')->first();
        $secretaria = TipoOrgao::where('slug', 'secretaria')->first();
        $fundacao = TipoOrgao::where('slug', 'fundacao')->first();
        $instituto = TipoOrgao::where('slug', 'instituto')->first();
        $empresaEstadual = TipoOrgao::where('slug', 'empresa_estadual')->first();

        $orgaosEstaduais = [
            // Governadoria
            ['tipo_orgao_id' => $governadoria->id, 'nome' => 'Casa Civil', 'sigla' => 'CC-BA'],
            ['tipo_orgao_id' => $governadoria->id, 'nome' => 'Secretaria de Comunicação', 'sigla' => 'SECOM-BA'],

            // Secretarias
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria da Saúde', 'sigla' => 'SESAB'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria da Educação', 'sigla' => 'SEC'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Infraestrutura', 'sigla' => 'SEINFRA'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Desenvolvimento Urbano', 'sigla' => 'SEDUR'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria da Fazenda', 'sigla' => 'SEFAZ'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Agricultura', 'sigla' => 'SEAGRI'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Turismo', 'sigla' => 'SETUR'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Cultura', 'sigla' => 'SECULT'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria do Meio Ambiente', 'sigla' => 'SEMA'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Justiça e Direitos Humanos', 'sigla' => 'SJDH'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria de Segurança Pública', 'sigla' => 'SSP'],
            ['tipo_orgao_id' => $secretaria->id, 'nome' => 'Secretaria do Trabalho, Emprego, Renda e Esporte', 'sigla' => 'SETRE'],

            // Fundações
            ['tipo_orgao_id' => $fundacao->id, 'nome' => 'Fundação Cultural do Estado da Bahia', 'sigla' => 'FUNCEB'],
            ['tipo_orgao_id' => $fundacao->id, 'nome' => 'Fundação Pedro Calmon', 'sigla' => 'FPC'],
            ['tipo_orgao_id' => $fundacao->id, 'nome' => 'Fundação Hospitalar do Estado da Bahia', 'sigla' => 'FHE'],

            // Institutos
            ['tipo_orgao_id' => $instituto->id, 'nome' => 'Instituto do Patrimônio Artístico e Cultural da Bahia', 'sigla' => 'IPAC'],
            ['tipo_orgao_id' => $instituto->id, 'nome' => 'Instituto do Meio Ambiente e Recursos Hídricos', 'sigla' => 'INEMA'],

            // Empresas
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Companhia de Desenvolvimento Urbano do Estado da Bahia', 'sigla' => 'CONDER'],
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Empresa Baiana de Águas e Saneamento', 'sigla' => 'EMBASA'],
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Companhia de Eletricidade do Estado da Bahia', 'sigla' => 'COELBA'],
            ['tipo_orgao_id' => $empresaEstadual->id, 'nome' => 'Empresa Baiana de Desenvolvimento Agrícola', 'sigla' => 'EBDA'],
        ];

        // Insere todos
        foreach (array_merge($orgaosFederais, $orgaosEstaduais) as $orgao) {
            OrgaoGoverno::updateOrCreate(
                ['sigla' => $orgao['sigla']],
                $orgao
            );
        }
    }
}
