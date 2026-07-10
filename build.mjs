import { build } from 'esbuild'
import { readFile, writeFile } from 'node:fs/promises'
import { format, resolveConfig } from 'prettier'

const distributionPath = new URL(
    'resources/dist/search-modal.js',
    import.meta.url,
)
const checkOnly = process.argv.includes('--check')
const result = await build({
    banner: {
        js: '// Generated from resources/js/search-modal.js. Run npm run build.',
    },
    bundle: true,
    entryPoints: ['resources/js/search-modal.js'],
    format: 'iife',
    platform: 'browser',
    target: ['es2022'],
    write: false,
})
const prettierConfig = (await resolveConfig(distributionPath.pathname)) ?? {}
const generatedAsset = await format(result.outputFiles[0].text, {
    ...prettierConfig,
    parser: 'babel',
    plugins: [],
})

if (checkOnly) {
    const committedAsset = await readFile(distributionPath, 'utf8')

    if (generatedAsset !== committedAsset) {
        throw new Error(
            'Search distribution asset is stale. Run npm run build and commit resources/dist/search-modal.js.',
        )
    }
} else {
    await writeFile(distributionPath, generatedAsset)
}
