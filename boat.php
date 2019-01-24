<!DOCTYPE HTML>
<html lang="en">
	<head>
		<title>Barco flotador</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
		<style type="text/css">
			body {
				background: #000;
				color: #999;
				padding: 0;
				margin: 0;
				overflow: hidden;
				font-family: georgia;
				font-size:1em;
				text-align: center;
			}
			#info { position: absolute; top: 10px; width: 100%; }
			a { color: #fb0; }
			#footer { position: absolute; bottom: 10px; width: 100%; }
			.h { color: #fb0 }
			.c { display: inline; margin-left: 1em }
		</style>
	</head>

	<body>
		<div id="container"></div>


		<div id="footer">
			<div class="c">
			day / night: <span class="h">n</span>
			</div>

			<div class="c">
			animate terrain: <span class="h">m</span>
			</div>
		</div>

		
		<script src="js/build/three.js"></script>

		<script src="js/examples/js/controls/OrbitControls.js"></script>
		<script src="js/examples/js/utils/BufferGeometryUtils.js"></script>

		<script src="js/examples/js/shaders/NormalMapShader.js"></script>
		<script src="js/examples/js/ShaderTerrain.js"></script>

		<script src="js/examples/js/WebGL.js"></script>
		<script src="js/libs/stats.min.js"></script>
		<script src="js/examples/js/libs/stats.min.js"></script>
		<script src="js/examples/js/objects/Water.js"></script>
		<script src="js/examples/js/ParametricGeometries.js"></script>
		<script id="fragmentShaderNoise" type="x-shader/x-fragment">
			
			uniform float time;
			varying vec2 vUv;
			vec4 permute( vec4 x ) {
				return mod( ( ( x * 34.0 ) + 1.0 ) * x, 289.0 );
			}
			vec4 taylorInvSqrt( vec4 r ) {
				return 1.79284291400159 - 0.85373472095314 * r;
			}
			float snoise( vec3 v ) {
				const vec2 C = vec2( 1.0 / 6.0, 1.0 / 3.0 );
				const vec4 D = vec4( 0.0, 0.5, 1.0, 2.0 );
				// First corner
				vec3 i  = floor( v + dot( v, C.yyy ) );
				vec3 x0 = v - i + dot( i, C.xxx );
				// Other corners
				vec3 g = step( x0.yzx, x0.xyz );
				vec3 l = 1.0 - g;
				vec3 i1 = min( g.xyz, l.zxy );
				vec3 i2 = max( g.xyz, l.zxy );
				vec3 x1 = x0 - i1 + 1.0 * C.xxx;
				vec3 x2 = x0 - i2 + 2.0 * C.xxx;
				vec3 x3 = x0 - 1. + 3.0 * C.xxx;
				// Permutations
				i = mod( i, 289.0 );
				vec4 p = permute( permute( permute(
						 i.z + vec4( 0.0, i1.z, i2.z, 1.0 ) )
					   + i.y + vec4( 0.0, i1.y, i2.y, 1.0 ) )
					   + i.x + vec4( 0.0, i1.x, i2.x, 1.0 ) );
				// Gradients
				// ( N*N points uniformly over a square, mapped onto an octahedron.)
				float n_ = 1.0 / 7.0; // N=7
				vec3 ns = n_ * D.wyz - D.xzx;
				vec4 j = p - 49.0 * floor( p * ns.z *ns.z );  //  mod(p,N*N)
				vec4 x_ = floor( j * ns.z );
				vec4 y_ = floor( j - 7.0 * x_ );    // mod(j,N)
				vec4 x = x_ *ns.x + ns.yyyy;
				vec4 y = y_ *ns.x + ns.yyyy;
				vec4 h = 1.0 - abs( x ) - abs( y );
				vec4 b0 = vec4( x.xy, y.xy );
				vec4 b1 = vec4( x.zw, y.zw );
				vec4 s0 = floor( b0 ) * 2.0 + 1.0;
				vec4 s1 = floor( b1 ) * 2.0 + 1.0;
				vec4 sh = -step( h, vec4( 0.0 ) );
				vec4 a0 = b0.xzyw + s0.xzyw * sh.xxyy;
				vec4 a1 = b1.xzyw + s1.xzyw * sh.zzww;
				vec3 p0 = vec3( a0.xy, h.x );
				vec3 p1 = vec3( a0.zw, h.y );
				vec3 p2 = vec3( a1.xy, h.z );
				vec3 p3 = vec3( a1.zw, h.w );
				// Normalise gradients
				vec4 norm = taylorInvSqrt( vec4( dot( p0, p0 ), dot( p1, p1 ), dot( p2, p2 ), dot( p3, p3 ) ) );
				p0 *= norm.x;
				p1 *= norm.y;
				p2 *= norm.z;
				p3 *= norm.w;
				// Mix final noise value
				vec4 m = max( 0.6 - vec4( dot( x0, x0 ), dot( x1, x1 ), dot( x2, x2 ), dot( x3, x3 ) ), 0.0 );
				m = m * m;
				return 42.0 * dot( m*m, vec4( dot( p0, x0 ), dot( p1, x1 ),
											  dot( p2, x2 ), dot( p3, x3 ) ) );
			}
			float surface3( vec3 coord ) {
				float n = 0.0;
				n += 1.0 * abs( snoise( coord ) );
				n += 0.5 * abs( snoise( coord * 2.0 ) );
				n += 0.25 * abs( snoise( coord * 4.0 ) );
				n += 0.125 * abs( snoise( coord * 8.0 ) );
				return n;
			}
			void main( void ) {
				vec3 coord = vec3( vUv, -time );
				float n = surface3( coord );
				gl_FragColor = vec4( vec3( n, n, n ), 1.0 );
			}
		</script>

		<script id="vertexShader" type="x-shader/x-vertex">
			varying vec2 vUv;
			uniform vec2 scale;
			uniform vec2 offset;
			void main( void ) {
				vUv = uv * scale + offset;
				gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );
			}
		</script>

		<script>
			if ( WEBGL.isWebGLAvailable() === false ) {
				document.body.appendChild( WEBGL.getWebGLErrorMessage() );
			}
			var SCREEN_WIDTH = window.innerWidth;
			var SCREEN_HEIGHT = window.innerHeight;
			var renderer, container, stats;
			var camera, scene, controls;
			var cameraOrtho, sceneRenderTarget;
			var uniformsNoise, uniformsNormal, uniformsTerrain,
				heightMap, normalMap,
				quadTarget;
			var directionalLight, pointLight;
			var terrain;
			var animDelta = 0, animDeltaDir = - 1;
			var lightVal = 0, lightDir = 1;
			var clock = new THREE.Clock();
			var updateNoise = true;
			var mlib = {};
			init();
			animate();
			function init() {
				container = document.getElementById( 'container' );
				// SCENE (RENDER TARGET)
				sceneRenderTarget = new THREE.Scene();
				cameraOrtho = new THREE.OrthographicCamera( SCREEN_WIDTH / - 2, SCREEN_WIDTH / 2, SCREEN_HEIGHT / 2, SCREEN_HEIGHT / - 2, - 10000, 10000 );
				cameraOrtho.position.z = 100;
				sceneRenderTarget.add( cameraOrtho );
				// CAMERA
				camera = new THREE.PerspectiveCamera( 40, SCREEN_WIDTH / SCREEN_HEIGHT, 2, 4000 );
				camera.position.set( - 1200, 800, 1200 );
				controls = new THREE.OrbitControls( camera );
				controls.rotateSpeed = 1.0;
				controls.zoomSpeed = 1.2;
				controls.panSpeed = 0.8;
				controls.keys = [ 65, 83, 68 ];
				// SCENE (FINAL)
				scene = new THREE.Scene();
				scene.background = new THREE.Color( 0x050505 );
				scene.fog = new THREE.Fog( 0x050505, 2000, 4000 );
				// LIGHTS
				scene.add( new THREE.AmbientLight( 0x111111 ) );
				directionalLight = new THREE.DirectionalLight( 0xffffff, 1.15 );
				directionalLight.position.set( 500, 2000, 0 );
				scene.add( directionalLight );
				pointLight = new THREE.PointLight( 0xff4400, 1.5 );
				pointLight.position.set( 0, 0, 0 );
				scene.add( pointLight );
				// HEIGHT + NORMAL MAPS
				var normalShader = THREE.NormalMapShader;
				var rx = 256, ry = 256;
				var pars = { minFilter: THREE.LinearFilter, magFilter: THREE.LinearFilter, format: THREE.RGBFormat };
				heightMap = new THREE.WebGLRenderTarget( rx, ry, pars );
				heightMap.texture.generateMipmaps = false;
				normalMap = new THREE.WebGLRenderTarget( rx, ry, pars );
				normalMap.texture.generateMipmaps = false;
				uniformsNoise = {
					time: { value: 1.0 },
					scale: { value: new THREE.Vector2( 1.5, 1.5 ) },
					offset: { value: new THREE.Vector2( 0, 0 ) }
				};
				uniformsNormal = THREE.UniformsUtils.clone( normalShader.uniforms );
				uniformsNormal.height.value = 0.05;
				uniformsNormal.resolution.value.set( rx, ry );
				uniformsNormal.heightMap.value = heightMap.texture;
				var vertexShader = document.getElementById( 'vertexShader' ).textContent;
				// TEXTURES
				var loadingManager = new THREE.LoadingManager( function () {
					terrain.visible = true;
				} );
				var textureLoader = new THREE.TextureLoader( loadingManager );
				var specularMap = new THREE.WebGLRenderTarget( 2048, 2048, pars );
				specularMap.texture.generateMipmaps = false;
				var diffuseTexture1 = textureLoader.load( "textures/terrain/grasslight-big.jpg" );
				var diffuseTexture2 = textureLoader.load( "textures/terrain/backgrounddetailed6.jpg" );
				var detailTexture = textureLoader.load( "textures/terrain/grasslight-big-nm.jpg" );
				diffuseTexture1.wrapS = diffuseTexture1.wrapT = THREE.RepeatWrapping;
				diffuseTexture2.wrapS = diffuseTexture2.wrapT = THREE.RepeatWrapping;
				detailTexture.wrapS = detailTexture.wrapT = THREE.RepeatWrapping;
				specularMap.texture.wrapS = specularMap.texture.wrapT = THREE.RepeatWrapping;
				// TERRAIN SHADER
				var terrainShader = THREE.ShaderTerrain[ "terrain" ];
				uniformsTerrain = THREE.UniformsUtils.clone( terrainShader.uniforms );
				uniformsTerrain[ 'tNormal' ].value = normalMap.texture;
				uniformsTerrain[ 'uNormalScale' ].value = 3.5;
				uniformsTerrain[ 'tDisplacement' ].value = heightMap.texture;
				uniformsTerrain[ 'tDiffuse1' ].value = diffuseTexture1;
				uniformsTerrain[ 'tDiffuse2' ].value = diffuseTexture2;
				uniformsTerrain[ 'tSpecular' ].value = specularMap.texture;
				uniformsTerrain[ 'tDetail' ].value = detailTexture;
				uniformsTerrain[ 'enableDiffuse1' ].value = true;
				uniformsTerrain[ 'enableDiffuse2' ].value = true;
				uniformsTerrain[ 'enableSpecular' ].value = true;
				uniformsTerrain[ 'diffuse' ].value.setHex( 0xffffff );
				uniformsTerrain[ 'specular' ].value.setHex( 0xffffff );
				uniformsTerrain[ 'shininess' ].value = 30;
				uniformsTerrain[ 'uDisplacementScale' ].value = 375;
				uniformsTerrain[ 'uRepeatOverlay' ].value.set( 6, 6 );
				var params = [
					[ 'heightmap', 	document.getElementById( 'fragmentShaderNoise' ).textContent, 	vertexShader, uniformsNoise, false ],
					[ 'normal', 	normalShader.fragmentShader, normalShader.vertexShader, uniformsNormal, false ],
					[ 'terrain', 	terrainShader.fragmentShader, terrainShader.vertexShader, uniformsTerrain, true ]
				 ];
				for ( var i = 0; i < params.length; i ++ ) {
					var material = new THREE.ShaderMaterial( {
						uniforms: params[ i ][ 3 ],
						vertexShader: params[ i ][ 2 ],
						fragmentShader: params[ i ][ 1 ],
						lights: params[ i ][ 4 ],
						fog: true
					} );
					mlib[ params[ i ][ 0 ] ] = material;
				}
				var axesHelper = new THREE.AxesHelper( 1000);
				axesHelper.position.set(0,0,0);
				scene.add( axesHelper );
				var plane = new THREE.PlaneBufferGeometry( SCREEN_WIDTH, SCREEN_HEIGHT );
				quadTarget = new THREE.Mesh( plane, new THREE.MeshBasicMaterial( { color: 0x000000 } ) );
				quadTarget.position.z = - 500;
				sceneRenderTarget.add( quadTarget );
				// TERRAIN MESH
				var geometryTerrain = new THREE.PlaneBufferGeometry( 6000, 6000, 256, 256 );
				THREE.BufferGeometryUtils.computeTangents( geometryTerrain );
				terrain = new THREE.Mesh( geometryTerrain, mlib[ 'terrain' ] );
				terrain.position.set( 0, - 125, 0 );
				terrain.rotation.x = - Math.PI / 2;
				terrain.visible = false;
				scene.add( terrain );
				// RENDERER
				renderer = new THREE.WebGLRenderer();
				renderer.setPixelRatio( window.devicePixelRatio );
				renderer.setSize( SCREEN_WIDTH, SCREEN_HEIGHT );
				container.appendChild( renderer.domElement );
				// STATS
				stats = new Stats();
				container.appendChild( stats.dom );
				// EVENTS
				onWindowResize();
				window.addEventListener( 'resize', onWindowResize, false );
				document.addEventListener( 'keydown', onKeyDown, false );
			}
			//WATER
			var waterGeometry = new THREE.PlaneBufferGeometry( 5000, 6000 );
			water = new THREE.Water(
					waterGeometry,
					{
						textureWidth: 512,
						textureHeight: 512,
						waterNormals: new THREE.TextureLoader().load( 'textures/waternormals.jpg', function ( texture ) {
							texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
						} ),
						alpha: 1.0,
						sunDirection: pointLight.position.clone().normalize(),
						sunColor: 0xffffff,
						waterColor: 0x001e0f,
						distortionScale: 3.7,
						fog: scene.fog !== undefined
					}
				);
				water.rotation.x = - Math.PI / 2;
				water.position.set(0,-5,0)
				scene.add(water)
			//BRIDGE
			var paraFun = function(u,v,eq){
				var x = -100 + 200 * u/2;
				var y = -100 + 200 * v/2;
				var z = (Math.sin(u * Math.PI) + Math.sin(v * Math.PI)) * -50/2;
				eq.set(x,y,z);
			}

			var cylinder = function(u,v,vec){
				console.log(u);
				var x = 200*Math.cos(Math.PI*u);
				var y = 200*Math.sin(Math.PI*v);
				var z = 200;

				vec.set(x,y,z);
			}

//SHIP Make a ship with basic geometrys

//APAREJO
var geometry = new THREE.ParametricGeometry(paraFun, 100, 100);
var material = new THREE.MeshPhongMaterial( { color: 0xeeffee, side: THREE.DoubleSide} );
var klein = new THREE.Mesh( geometry, material );
klein.side = true;
klein.position.set(550, 180, -600);
klein.rotation.y =-Math.PI /4;

//wood texture
var loader = new THREE.TextureLoader();
var groundTexture = loader.load( 'js/examples/textures/terrain/wood.jpg' );

//BOTE

//BACK
var cylinder = new THREE.CylinderBufferGeometry(100, 100, 200, 16, 4, false, 0, Math.PI);
var mat_c = new THREE.MeshPhongMaterial({side: THREE.DoubleSide, map:groundTexture});
var cyl = new THREE.Mesh( cylinder, mat_c );
cyl.rotation.x = Math.PI/2;
cyl.rotation.y = -Math.PI/2;
cyl.position.y = -150;
cyl.position.x = -50;
klein.add(cyl);	

//PLANO
var plane_surface = new THREE.PlaneBufferGeometry(185, 200 );
var mat_p = new THREE.MeshPhongMaterial({side: THREE.DoubleSide, map:groundTexture});
var pla = new THREE.Mesh( plane_surface, mat_p );
pla.rotation.x = Math.PI/2;
pla.position.y = -175;
pla.position.x = -50;
klein.add(pla);

//Front
var geometry_cone = new THREE.ConeBufferGeometry( 100, 100, 32,32, true, 0, Math.PI );
var material_c = new THREE.MeshPhongMaterial({side: THREE.DoubleSide, map:groundTexture});
var cone = new THREE.Mesh( geometry_cone, material_c );
cone.rotation.x = -Math.PI/2;
cone.rotation.y = Math.PI/2;
cone.position.y = -150;
cone.position.z = -150;
cone.position.x = -50;
klein.add( cone );

function remos(u,v,vector){
var x = Math.cos(2*Math.PI*u);
var y = Math.sin(Math.PI*v);
var z = Math.sin(2*Math.PI*u) + Math.cos(Math.PI*v);
vector.set(10*x,10*y,10*z);

}
var geometry_sin = new THREE.ParametricGeometry(remos, 100, 100);
var material_sin = new THREE.MeshPhongMaterial( { map:groundTexture, side: THREE.DoubleSide, color:0x000000} );
var con_sin = new THREE.Mesh( geometry_sin, material_sin );
con_sin.position.y =-150;
con_sin.position.x =50;
var con_sin2 = con_sin.clone();
con_sin2.position.y =-150;
con_sin2.position.x =-150;
klein.add(con_sin);
klein.add(con_sin2);

//FLAG
var mg = 5;
function flag(u,v,vector)
{
	var x = 10*u;
	var y = 10*v;
	var z = Math.sin(2*Math.PI*u) ;
	vector.set(mg *x,mg *y,mg *z);
}
var geometry_sin = new THREE.ParametricGeometry(flag, 100, 100);
var material_sin = new THREE.MeshPhongMaterial( { side: THREE.DoubleSide, color:0xff1010} );
var flag = new THREE.Mesh( geometry_sin, material_sin );


//TEXT
var loader = new THREE.FontLoader();
loader.load( 'js/examples/fonts/helvetiker_regular.typeface.json', function ( font ) {
	var geometry = new THREE.TextGeometry( 'Mon', {
		font: font,
		size: mg *1,
		height: mg *0.5,
		curveSegments: 12,
		bevelEnabled: false,
		bevelThickness: 0.1,
		bevelSize: 0.1,
		bevelSegments: 0.1
	} );
	var txt_mat = new THREE.MeshPhongMaterial({color:0xffffff});
	var txt_mesh = new THREE.Mesh(geometry, txt_mat);
	txt_mesh.position.z = mg * 0.2;
	txt_mesh.position.y = mg * 5;
	txt_mesh.rotation.y = -Math.PI/8;
	flag.add(txt_mesh);
	var geometry = new THREE.TextGeometry( 'tiel', {
		font: font,
		size: mg *1,
		height: mg *0.5,
		curveSegments: 12,
		bevelEnabled: false,
		bevelThickness: 0.1,
		bevelSize: 0.1,
		bevelSegments: 0.1
	} );
	var txt_mat = new THREE.MeshPhongMaterial({color:0xffffff});
	var txt_mesh = new THREE.Mesh(geometry, txt_mat);
	txt_mesh.position.z =mg * 1.2;
	txt_mesh.position.x = mg *2.5;
	txt_mesh.position.y =mg * 5;
	txt_mesh.rotation.y = Math.PI/12;
	flag.add(txt_mesh);
	var geometry = new THREE.TextGeometry( '$oft', {
		font: font,
		size: mg *1,
		height: mg *0.5,
		curveSegments: 12,
		bevelEnabled: false,
		bevelThickness: 0.1,
		bevelSize: 0.1,
		bevelSegments: 0.1
	} );
	var txt_mat = new THREE.MeshPhongMaterial({color:0xffffff});
	var txt_mesh = new THREE.Mesh(geometry, txt_mat);
	txt_mesh.position.z =mg * 0.28;
	txt_mesh.position.x = mg *4.5;
	txt_mesh.position.y =mg * 5;
	txt_mesh.rotation.y = Math.PI/7;
	flag.add(txt_mesh);
	var geometry = new THREE.TextGeometry( 'Ware', {
		font: font,
		size: mg *1,
		height: mg *0.5,
		curveSegments: mg *12,
		bevelEnabled: false,
		bevelThickness: 0.1,
		bevelSize: 0.1,
		bevelSegments: 0.1
	} );
	var txt_mat = new THREE.MeshPhongMaterial({color:0xffffff});
	var txt_mesh = new THREE.Mesh(geometry, txt_mat);
	txt_mesh.position.z =mg * -1;
	txt_mesh.position.x =mg * 7;
	txt_mesh.position.y =mg * 5;
	txt_mesh.rotation.y = -Math.PI/8;
	flag.add(txt_mesh);

} );
flag.position.z=-150;
flag.position.x=-50;
klein.add(flag);

//SOSTENEDOR DE APAREJO
var cylinder = new THREE.CylinderBufferGeometry(7, 7, 150, 16, 4);
var mat_c = new THREE.MeshPhongMaterial({side: THREE.DoubleSide, map:groundTexture});
var cyl = new THREE.Mesh( cylinder, mat_c );
cyl.position.y = -150;
cyl.position.x = -50;
klein.add(cyl);

//SOSTENEDOR DE BANDERA
var cylinder = new THREE.CylinderBufferGeometry(4, 4, 225, 16, 4);
var mat_c = new THREE.MeshPhongMaterial({side: THREE.DoubleSide, map:groundTexture, color:0x000000});
var cyl = new THREE.Mesh( cylinder, mat_c );
cyl.position.y = -75;
cyl.position.x = -53;
cyl.position.z = -150;
klein.add(cyl);

scene.add( klein );	

			//
			function onWindowResize() {
				SCREEN_WIDTH = window.innerWidth;
				SCREEN_HEIGHT = window.innerHeight;
				renderer.setSize( SCREEN_WIDTH, SCREEN_HEIGHT );
				camera.aspect = SCREEN_WIDTH / SCREEN_HEIGHT;
				camera.updateProjectionMatrix();
			}
			//
			function onKeyDown( event ) {
				switch ( event.keyCode ) {
					case 78: /*N*/ lightDir *= - 1; break;
					case 77: /*M*/ animDeltaDir *= - 1; break;
				}
			}
			//
			function animate() {

				requestAnimationFrame( animate );
				render();
				stats.update();
			}
			var rotator = true;
			var statc = true;
			var statc2 = true;
			function render() {

				var delta = clock.getDelta();
				if ( terrain.visible ) {
					var fLow = 0.1, fHigh = 0.8;
					lightVal = THREE.Math.clamp( lightVal + 0.5 * delta * lightDir, fLow, fHigh );
					var valNorm = ( lightVal - fLow ) / ( fHigh - fLow );
					scene.background.setHSL( 0.1, 0.5, lightVal );
					scene.fog.color.setHSL( 0.1, 0.5, lightVal );
					directionalLight.intensity = THREE.Math.mapLinear( valNorm, 0, 1, 0.1, 1.15 );
					pointLight.intensity = THREE.Math.mapLinear( valNorm, 0, 1, 0.9, 1.5 );
					if (klein.position.x <=650) {

						klein.position.x += 0.15;
						klein.rotation.y +=  0.0008;
					}
					else{
						if (rotator) {
						if (klein.rotation.y <= 1 ) 
						{
							rotator = false;
							console.log(rotator);
						}

					}
					if (!rotator) 
					{	if (klein.position.z >= -980) {
					
						klein.position.z -= 0.15;
						klein.rotation.y -=  0.00008;
					}
					else{
						statc = false;
						statc2 = false;
					}
					if (!statc) {
						klein.rotation.x +=0.01;
		
						statc2 = true;
				
					}
					if (statc2) {
						klein.rotation.x -=0.01;

						statc2 = false;
		
					}

					}
					}
					uniformsTerrain[ 'uNormalScale' ].value = THREE.Math.mapLinear( valNorm, 0, 1, 0.6, 3.5 );
					if ( updateNoise ) {
						animDelta = THREE.Math.clamp( animDelta + 0.00075 * animDeltaDir, 0, 0.05 );
						uniformsNoise[ 'time' ].value += delta * animDelta;
						//uniformsNoise[ 'offset' ].value.x += delta * 0.05;
						//uniformsTerrain[ 'uOffset' ].value.x = 4 * uniformsNoise[ 'offset' ].value.x;
						quadTarget.material = mlib[ 'heightmap' ];
						renderer.render( sceneRenderTarget, cameraOrtho, heightMap, true );
						quadTarget.material = mlib[ 'normal' ];
						renderer.render( sceneRenderTarget, cameraOrtho, normalMap, true );
					}
					renderer.render( scene, camera );
				}
			}
		</script>

	</body>
</html>
<?
//CREADOR DE ANIMACION DE TERRENO
			// Description : Array and textureless GLSL 3D simplex noise function.
			//      Author : Ian McEwan, Ashima Arts.
			//  Maintainer : ijm
			//     Lastmod : 20110409 (stegu)
			//     License : Copyright (C) 2011 Ashima Arts. All rights reserved.
			//               Distributed under the MIT License. See LICENSE file.
			//
?>
